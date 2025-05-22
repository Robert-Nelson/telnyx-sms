<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2025 Robert Nelson <robert-telnyx-sms@nelson.house>
//
if (!defined('FREEPBX_IS_AUTH')) {
	die('No direct script access allowed');
}

echo FreePBX::Telnyx_sms()->showPage();
