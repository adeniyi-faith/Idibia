with open('book-trip-handler.php', 'r') as f:
    content = f.read()

old_platform_pct = """$platform_pct = (int) get_option( 'sd_platform_commission_pct', 20 );"""
new_platform_pct = """$platform_pct = (int) ( $quote['platform_pct'] ?? idibia_get_setting('platform_commission_pct', 20) );"""
content = content.replace(old_platform_pct, new_platform_pct)

with open('book-trip-handler.php', 'w') as f:
    f.write(content)
