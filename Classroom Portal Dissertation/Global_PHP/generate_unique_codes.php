<?php
// Generate a unique code
function generateUniqueCode() {
    return substr(md5(uniqid(rand(), true)), 0, 8); // Generates an 8-character unique code
}

$uniqueCode = generateUniqueCode();
echo "Generated Unique Code: $uniqueCode";
?>