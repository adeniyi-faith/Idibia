import sys

with open("book-trip-handler.php", "r") as f:
    content = f.read()

# Add rate limit back securely
rate_limit_block = """$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
if ( ! idibia_check_rate_limit( 'booking', $ip, 10, 60 ) ) {
    http_response_code( 429 );
    wp_send_json_error( [ 'message' => 'Too many requests. Please try again later.' ] );
}"""

# Insert after method check
post_check_end = content.find("    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );\n}\n")
if post_check_end != -1:
    insertion_point = post_check_end + len("    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );\n}\n")
    new_content = content[:insertion_point] + "\n" + rate_limit_block + "\n" + content[insertion_point:]

    # Needs idibia-helpers.php included
    new_content = new_content.replace(
        "require_once __DIR__ . '/wp-auth-config.php';",
        "require_once __DIR__ . '/wp-auth-config.php';\nrequire_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';"
    )

    # Also needs to remove the duplicate nonce check we previously stripped manually but might have come back
    # Wait, in the manual step I did `sed -i '/\$nonce = isset( \$_POST\['\''_nonce'\''\] )/,/}/d' book-trip-handler.php`
    # But then my `patch_book_trip.py` used the original contents before sed? No, my python script read the file.
    # Actually, `patch_book_trip.py` just appended the replacement without removing the nonce if it was already there.
    # Let's remove the nonce block completely.

    import re
    new_content = re.sub(r'\$nonce = isset.*?}\n', '', new_content, flags=re.DOTALL)

    with open("book-trip-handler.php", "w") as f:
        f.write(new_content)
