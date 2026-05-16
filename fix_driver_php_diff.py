import re

with open('driver.php', 'r') as f:
    content = f.read()

# Make sure we didn't accidentally remove renderDriverOffers body
# We already checked it was fine, but let's be sure.
print(content.find('function renderDriverOffers(offers, activeTrip = null) {'))
