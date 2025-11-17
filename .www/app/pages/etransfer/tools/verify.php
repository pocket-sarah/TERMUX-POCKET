<?php
session_set_cookie_params(['path'=>'/', 'httponly'=>true, 'samesite'=>'Lax']);
session_start();
header('Content-Type: application/json');

$posted_otp = $_POST['otp'] ?? '';

if(!isset($_SESSION['otp']) || !isset($_SESSION['otp_expires'])){
    echo json_encode(['success'=>false,'message'=>'No OTP session found']);
    exit;
}

if(time() > $_SESSION['otp_expires']){
    unset($_SESSION['otp'], $_SESSION['otp_expires']);
    echo json_encode(['success'=>false,'message'=>'OTP expired']);
    exit;
}

if($posted_otp !== $_SESSION['otp']){
    echo json_encode(['success'=>false,'message'=>'Incorrect OTP']);
    exit;
}

// OTP valid
unset($_SESSION['otp'], $_SESSION['otp_expires']);
echo json_encode(['success'=>true,'message'=>'OTP verified']);