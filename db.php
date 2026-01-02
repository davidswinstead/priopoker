<?php
$conn = new mysqli("localhost", "a2bremov_tools", "wankerwanker", "a2bremov_tools");
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}
