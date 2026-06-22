<?php
/**
 * Idibia — Minimal PDF generator for receipts and earnings statements.
 * Pure PHP, no external dependencies. Outputs valid PDF 1.4 (A4).
 */

if ( ! class_exists( 'IdibiaPDF' ) ) :

class IdibiaPDF {

    private array  $pages    = [];   // Finalised page content streams
    private string $cur      = '';   // Current page buffer
    private float  $y        = 0.0;  // Y cursor from top-left (increases downward)
    private float  $font_sz  = 12.0;
    private string $font_key = 'F1'; // F1=Helvetica, F2=Helvetica-Bold, F3=Helvetica-Oblique

    const PW = 595.28;  // A4 width in points  (210 mm)
    const PH = 841.89;  // A4 height in points (297 mm)
    const ML = 50.0;    // Left margin
    const MR = 50.0;    // Right margin
    const MT = 50.0;    // Top margin

    /** Content width between margins. */
    public function cw(): float {
        return self::PW - self::ML - self::MR;
    }

    public function __construct() {
        $this->new_page();
    }

    // ── Page management ────────────────────────────────────

    public function new_page(): void {
        if ( $this->cur !== '' ) {
            $this->pages[] = $this->cur;
        }
        $this->cur = '';
        $this->y   = self::MT;
    }

    // ── Font ──────────────────────────────────────────────

    /**
     * Set the active font.
     * @param string $style  '' = regular, 'B' = bold, 'I' = italic
     * @param float  $size   Point size
     */
    public function set_font( string $style, float $size ): void {
        $this->font_key = match ( strtoupper( $style ) ) {
            'B'     => 'F2',
            'I'     => 'F3',
            default => 'F1',
        };
        $this->font_sz = $size;
    }

    // ── Y cursor ──────────────────────────────────────────

    public function get_y(): float  { return $this->y; }
    public function set_y( float $y ): void { $this->y = $y; }
    public function ln( float $h ): void { $this->y += $h; }

    /**
     * Break to a new page if the next $h points would exceed the printable area.
     */
    public function check_break( float $h = 20.0 ): void {
        if ( $this->y + $h > self::PH - 45.0 ) {
            $this->new_page();
        }
    }

    // ── Drawing primitives ─────────────────────────────────

    /**
     * Write text at absolute PDF coordinates (top-down y).
     * Optionally override the active font key and size for this call only.
     */
    public function text(
        float   $x,
        float   $y,
        string  $text,
        ?string $override_key = null,
        ?float  $override_sz  = null
    ): void {
        $fk = $override_key ?? $this->font_key;
        $fs = $override_sz  ?? $this->font_sz;
        $py = self::PH - $y;
        $t  = $this->esc( $text );
        $this->cur .= "BT /{$fk} {$fs} Tf {$x} {$py} Td ({$t}) Tj ET\n";
    }

    /**
     * Write text right-aligned so its right edge lands at $x.
     * Width estimated using Helvetica avg-advance heuristic.
     */
    public function text_r(
        float   $x,
        float   $y,
        string  $text,
        ?string $ok = null,
        ?float  $os = null
    ): void {
        $sz = $os ?? $this->font_sz;
        $w  = $this->str_width( $text, $sz, $ok ?? $this->font_key );
        $this->text( $x - $w, $y, $text, $ok, $os );
    }

    /** Draw a horizontal rule across the content area. */
    public function hline( float $y, ?float $x1 = null, ?float $x2 = null, float $lw = 0.4 ): void {
        $x1 = $x1 ?? self::ML;
        $x2 = $x2 ?? ( self::PW - self::MR );
        $py = self::PH - $y;
        $this->cur .= "{$lw} w {$x1} {$py} m {$x2} {$py} l S\n";
    }

    /**
     * Draw a filled rectangle.
     * @param float $r,$g,$b  Fill colour in 0–1 range
     */
    public function fill_rect( float $x, float $y, float $w, float $h, float $r, float $g, float $b ): void {
        $py = self::PH - $y - $h;
        $this->cur .= "{$r} {$g} {$b} rg {$x} {$py} {$w} {$h} re f\n0 0 0 rg\n";
    }

    // ── String metrics ─────────────────────────────────────

    /**
     * Approximate string width in points for Helvetica or Helvetica-Bold.
     * Bold characters are ~5 % wider than regular.
     */
    public function str_width( string $s, ?float $sz = null, ?string $font_key = null ): float {
        $sz       = $sz       ?? $this->font_sz;
        $font_key = $font_key ?? $this->font_key;
        $mul      = ( $font_key === 'F2' ) ? 0.58 : 0.55;
        return mb_strlen( $s, 'UTF-8' ) * $sz * $mul;
    }

    // ── PDF output ─────────────────────────────────────────

    /** Build and return the raw PDF byte string. */
    public function output(): string {
        if ( $this->cur !== '' ) {
            $this->pages[] = $this->cur;
            $this->cur     = '';
        }

        $n_pages = count( $this->pages );
        if ( $n_pages === 0 ) {
            $this->pages[] = '';
            $n_pages       = 1;
        }

        // Object layout (1-indexed, contiguous):
        //  1          = Catalog
        //  2          = Pages node
        //  3..n+2     = Page objects (one per page)
        //  n+3..2n+2  = Content streams (one per page)
        //  2n+3       = Font F1 (Helvetica)
        //  2n+4       = Font F2 (Helvetica-Bold)
        //  2n+5       = Font F3 (Helvetica-Oblique)

        $page_base    = 3;
        $content_base = $page_base    + $n_pages;
        $f1_id        = $content_base + $n_pages;
        $f2_id        = $f1_id + 1;
        $f3_id        = $f2_id + 1;
        $total_obj    = $f3_id;

        $body    = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $offsets = [];

        $add = function ( int $id, string $dict, string $stream = '' ) use ( &$body, &$offsets ): void {
            $offsets[ $id ] = strlen( $body );
            if ( $stream !== '' ) {
                $len   = strlen( $stream );
                $body .= "{$id} 0 obj\n{$dict}\nstream\n{$stream}\nendstream\nendobj\n";
            } else {
                $body .= "{$id} 0 obj\n{$dict}\nendobj\n";
            }
        };

        // 1: Catalog
        $add( 1, '<< /Type /Catalog /Pages 2 0 R >>' );

        // 2: Pages node
        $kids = implode( ' ', array_map(
            fn( $i ) => ( $page_base + $i ) . ' 0 R',
            range( 0, $n_pages - 1 )
        ) );
        $add( 2, "<< /Type /Pages /Kids [{$kids}] /Count {$n_pages} >>" );

        // Page & content pairs
        $pw = self::PW;
        $ph = self::PH;
        for ( $i = 0; $i < $n_pages; $i++ ) {
            $pid = $page_base    + $i;
            $cid = $content_base + $i;

            $add( $pid,
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pw} {$ph}] "
                . "/Resources << /Font << /F1 {$f1_id} 0 R /F2 {$f2_id} 0 R /F3 {$f3_id} 0 R >> >> "
                . "/Contents {$cid} 0 R >>"
            );

            $stream = $this->pages[ $i ];
            $len    = strlen( $stream );
            $add( $cid, "<< /Length {$len} >>", $stream );
        }

        // Font descriptors
        $add( $f1_id, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>' );
        $add( $f2_id, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>' );
        $add( $f3_id, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>' );

        // Cross-reference table
        $xref_pos = strlen( $body );
        $body    .= "xref\n0 " . ( $total_obj + 1 ) . "\n";
        $body    .= "0000000000 65535 f \n";
        for ( $i = 1; $i <= $total_obj; $i++ ) {
            $body .= sprintf( "%010d 00000 n \n", $offsets[ $i ] ?? 0 );
        }
        $body .= "trailer\n<< /Size " . ( $total_obj + 1 ) . " /Root 1 0 R >>\n";
        $body .= "startxref\n{$xref_pos}\n%%EOF\n";

        return $body;
    }

    // ── Internal helpers ───────────────────────────────────

    private function esc( string $s ): string {
        // Naira sign is not in WinAnsi encoding; render as "NGN"
        $s = str_replace( '₦', 'NGN', $s );
        // Transliterate UTF-8 to Windows-1252 (covers accented Latin chars)
        $converted = iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s );
        $s = ( $converted !== false ) ? $converted : $s;
        return str_replace( [ '\\', '(', ')' ], [ '\\\\', '\\(', '\\)' ], $s );
    }
}

endif; // class_exists IdibiaPDF
