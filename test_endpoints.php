<?php
// We just want to make sure there are no syntax errors in api.php
// By linting it.
echo exec('php -l admin/api.php');
