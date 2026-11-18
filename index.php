<?php
session_start();

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] == "admin") {
        header("location:admin/dashboard.php");
    } else {
        header("location:user/dashboard.php");
    }
} else {
    header("location:login.php");
}
