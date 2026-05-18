with open('admin.php', 'r') as f:
    content = f.read()

# Fix the injected backslashes in javascript toggle
content = content.replace(r"onclick=\"this.classList.toggle(\'on\')\"", r"onclick=\"this.classList.toggle('on')\"")

with open('admin.php', 'w') as f:
    f.write(content)
