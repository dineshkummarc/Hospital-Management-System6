<?php
// Reuse the patient print prescription page
require_once '../config.php';
requireAuth('admin');
include '../patient/print_prescription.php';
