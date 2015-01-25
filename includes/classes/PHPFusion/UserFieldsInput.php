<?php
/*-------------------------------------------------------+
| PHP-Fusion Content Management System
| Copyright (C) PHP-Fusion Inc
| https://www.php-fusion.co.uk/
+--------------------------------------------------------+
| Filename: UserFieldsInput.php
| Author: Hans Kristian Flaatten (Starefossen)
+--------------------------------------------------------+
| This program is released as free software under the
| Affero GPL license. You can redistribute it and/or
| modify it under the terms of this license which you
| can read by viewing the included agpl.txt or online
| at www.gnu.org/licenses/agpl.html. Removal of this
| copyright header is strictly prohibited without
| written permission from the original author(s).
+--------------------------------------------------------*/

namespace PHPFusion;

//@todo: merge user fields into 1 single file.
class UserFieldsInput {
	public $adminActivation = 1;
	public $emailVerification = 1;
	public $verifyNewEmail = FALSE;
	public $userData;
	public $validation = 0;
	public $registration = FALSE;
	// On insert or admin edit
	public $skipCurrentPass = FALSE; // FALSE to skip pass. True to validate password. New Register always FALSE.
	private $_completeMessage;
	private $_method;
	private $_noErrors = TRUE;
	private $_userEmail;
	private $_userHideEmail;
	private $_userName;
	// New for UF 2.00
	private $data = array();

	// Passwords
	private $_isValidCurrentPassword = FALSE;
	private $_isValidCurrentAdminPassword = FALSE;
	private $_userHash = FALSE;
	private $_userPassword = FALSE;
	private $_newUserPassword = FALSE;
	private $_newUserPassword2 = FALSE;
	private $_newUserPasswordHash = FALSE;
	private $_newUserPasswordSalt = FALSE;
	private $_newUserPasswordAlgo = FALSE;
	private $_userAdminPassword = FALSE;
	private $_newUserAdminPassword = FALSE;
	private $_newUserAdminPassword2 = FALSE;

	// User Log System
	private $_userLogData = array();
	private $_userLogFields = array();
	// Settings
	private $_userNameChange = TRUE;
	// Flags
	private $_themeChanged = FALSE;

	public function saveInsert() {
		$this->_method = "validate_insert";
		$this->data = array(
			"user_password" => "",
			"user_algo" => "",
			"user_salt" => "",
			"user_admin_password" => "",
			"user_admin_algo" => "",
			"user_admin_salt" => "",
			"user_name" => "",
			"user_email" => ""
		);
		if ($this->_userNameChange) {
			$this->_settUserName();
		}
		$this->_setPassword();
		$this->_setUserEmail();
		if ($this->validation == 1) $this->_setValidationError();
		$this->_setEmptyFields();
		if (!defined('FUSION_NULL')) {
			if ($this->emailVerification) {
				$this->_setEmailVerification();
			} else {
				$this->_setUserDataInput();
			}
		}
	}

	public function saveUpdate() {
		$this->_method = "validate_update";
		$this->data = $this->userData;
		$this->_settUserName();
		$this->_setPassword();
		$this->_setAdminPassword();
		$this->_setUserEmail();
		if ($this->validation == 1) $this->_setValidationError();
		$this->_setEmptyFields();
		$this->_setUserAvatar();
		if (!defined('FUSION_NULL')) $this->_setUserDataUpdate();
	}

	public function displayMessages() {
		global $locale;
		$title = ''; $message = '';
		if (!defined('FUSION_NULL')) {
			if ($this->_method == "validate_insert") {
				$title = $locale['u170'];
				$message = "<br />\n".$this->_completeMessage."<br /><br />\n";
			} else {
				$title = $locale['u169'];
				$message = "<br />\n".$this->_completeMessage."<br /><br />\n";
			}
			opentable($title);
			echo $message;
			closetable();
		}
	}

	public function setUserNameChange($value) {
		$this->_userNameChange = $value;
	}

	public function verifyCode($value) {
		global $locale, $userdata;
		if (!preg_check("/^[0-9a-z]{32}$/i", $value)) redirect("index.php");
		$result = dbquery("SELECT * FROM ".DB_EMAIL_VERIFY." WHERE user_code='".$value."'");
		if (dbrows($result)) {
			$data = dbarray($result);
			if ($data['user_id'] == $userdata['user_id']) {
				if ($data['user_email'] != $userdata['user_email']) {
					$result = dbquery("SELECT user_email FROM ".DB_USERS." WHERE user_email='".$data['user_email']."'");
					if (dbrows($result)) {
						$this->_noErrors = FALSE;
						$this->_errorMessages[0] = $locale['u164']."<br />\n".$locale['u121'];
					} else {
						$this->_completeMessage = $locale['u169'];
					}
					$result = dbquery("UPDATE ".DB_USERS." SET user_email='".$data['user_email']."' WHERE user_id='".$data['user_id']."'");
					$result = dbquery("DELETE FROM ".DB_EMAIL_VERIFY." WHERE user_id='".$data['user_id']."'");
				}
			} else {
				redirect("index.php");
			}
		} else {
			redirect("index.php");
		}
	}

	public function themeChanged() {
		return $this->_themeChanged;
	}

	private function _setFields($field_name, $unique = '0', $db_name = FALSE) {
		global $defender;
		$db_name = $db_name ? $db_name : DB_USERS;
		$input_var = isset($_POST[$field_name]) ? form_sanitizer($_POST[$field_name], 0, $field_name) : $this->data[$field_name];
		if ($unique && !defined("FUSION_NULL")) {
			if ($input_var && $input_var != $this->userData[$field_name]) {
				// check for the field name where this field value does not exist.
				$rows = dbcount("(".$field_name.")", $db_name, "".$field_name."='".$input_var."'");
				if (!$rows) {
					$this->data[$field_name] = $input_var;
				} else {
					$defender->stop();
					$defender->addError($field_name);
					$defender->addNotice($field_name." is already taken.");
				}
			} else {
				// set back the old value.
				$this->data[$field_name] = $this->userData[$field_name];
			}
		} else {
			if (!defined("FUSION_NULL")) {
				$this->data[$field_name] = $input_var;
			} else {
				$this->data[$field_name] = $this->userData[$field_name];
			}
		}
	}

	private function _settUserName() {
		global $locale, $defender;
		$this->_userName = isset($_POST['user_names']) ? stripinput(trim(preg_replace("/ +/i", " ", $_POST['user_names']))) : "";
		if ($this->_userName && $this->_userName != $this->userData['user_names']) {
			// attempt to change user name
			if (!preg_check("/^[-0-9A-Z_@\s]+$/i", $this->_userName)) {
				$defender->stop();
				$defender->addError('user_name');
				$defender->addHelperText('user_name', $locale['u120']);
				$defender->addNotice($locale['u120']);
			} else {
				$name_active = dbcount("(user_id)", DB_USERS, "user_name='".$this->_userName."'");
				$name_inactive = dbcount("(user_code)", DB_NEW_USERS, "user_name='".$this->_userName."'");
				if ($name_active == 0 && $name_inactive == 0) {
					$this->_userLogFields[] = "user_name";
					$this->data['user_name'] = $this->_userName;
				} else {
					$defender->stop();
					$defender->addError('user_name');
					$defender->addHelperText('user_name', $locale['u121']);
					$defender->addNotice($locale['u121']);
				}
			}
		} else {
			// User Name Cannot Be Left Empty on Register mode
			if ($this->_method != 'validate_update') {
				$defender->stop();
				$defender->addError('user_name');
				$defender->addHelperText('user_name', $locale['u122']);
				$defender->addNotice($locale['u122']);
			} else {
				$this->data['user_name'] = $this->_userName;
			}
		}
	}

	// Get New Password Hash and Directly Set New Cookie if Authenticated
	private function _setPassword() {
		global $locale, $defender;
		if ($this->registration) {
			// register have 2 fields
			$this->_newUserPassword = self::_getPasswordInput('user_password1');
			$this->_newUserPassword2 = self::_getPasswordInput('user_password2');
			if ($this->_newUserPassword) {
				// Intialize password auth
				$passAuth = new PasswordAuth();
				$passAuth->inputNewPassword = $this->_newUserPassword;
				$passAuth->inputNewPassword2 = $this->_newUserPassword2;
				$_isValidNewPassword = $passAuth->isValidNewPassword();
				switch($_isValidNewPassword) {
					case '0':
						// New password is valid
						$this->_newUserPasswordHash = $passAuth->getNewHash();
						$this->_newUserPasswordAlgo = $passAuth->getNewAlgo();
						$this->_newUserPasswordSalt = $passAuth->getNewSalt();
						$this->data['user_algo'] = $this->_newUserPasswordAlgo;
						$this->data['user_salt'] = $this->_newUserPasswordSalt;
						$this->data['user_password'] = $this->_newUserPasswordHash;
						$this->_isValidCurrentPassword  = 1;
						if (!defined('ADMIN_PANEL') && !$this->skipCurrentPass) {
							Authenticate::setUserCookie($this->userData['user_id'], $passAuth->getNewSalt(), $passAuth->getNewAlgo(), FALSE);
						}
						break;
					case '1':
						// New Password equal old password
						$defender->stop();
						$defender->addError('user_password');
						$defender->addError('user_new_password');
						$defender->addNotice($locale['u134'].$locale['u146'].$locale['u133'].".");
						break;
					case '2':
						// The two new passwords are not identical
						$defender->stop();
						$defender->addError('user_new_password');
						$defender->addError('user_new_password2');
						$defender->addHelperText('user_password', $locale['u148']);
						$defender->addNotice($locale['u148']);
						break;
					case '3':
						// New password contains invalid chars / symbols
						$defender->stop();
						$defender->addError('user_new_password');
						$defender->addHelperText('user_password', $locale['u134'].$locale['u142']."<br />".$locale['u147']);
						$defender->addNotice($locale['u134'].$locale['u142']."<br />".$locale['u147']);
						break;
				}
			} else {
				$defender->stop();
				$defender->addError('user_new_password');
				$defender->addHelperText('user_new_password', $locale['u134'].$locale['u143a']);
				$defender->addNotice($locale['u134'].$locale['u143a']);
			}
		} else {
			// edit profile have 3 fields
			$this->_userPassword = self::_getPasswordInput('user_password');
			$this->_newUserPassword = self::_getPasswordInput('user_password1');
			$this->_newUserPassword2 = self::_getPasswordInput('user_password2');
			// check password integrity
			if ($this->_userPassword) {
				// Intialize password auth
				$passAuth = new PasswordAuth();
				$passAuth->inputPassword = $this->_userPassword;
				$passAuth->inputNewPassword = $this->_newUserPassword;
				$passAuth->inputNewPassword2 = $this->_newUserPassword2;
				$passAuth->currentPasswordHash = $this->userData['user_password'];
				$passAuth->currentAlgo = $this->userData['user_algo'];
				$passAuth->currentSalt = $this->userData['user_salt'];
				if ($passAuth->isValidCurrentPassword()) {
					$this->_isValidCurrentPassword  = 1;
					$_isValidNewPassword = $passAuth->isValidNewPassword();
					switch($_isValidNewPassword) {
					case '0':
						// New password is valid
						$this->_newUserPasswordHash = $passAuth->getNewHash();
						$this->_newUserPasswordAlgo = $passAuth->getNewAlgo();
						$this->_newUserPasswordSalt = $passAuth->getNewSalt();
						$this->data['user_algo'] = $this->_newUserPasswordAlgo;
						$this->data['user_salt'] = $this->_newUserPasswordSalt;
						$this->data['user_password'] = $this->_newUserPasswordHash;
						if (!defined('ADMIN_PANEL') && !$this->skipCurrentPass) {
							//Authenticate::setUserCookie($this->userData['user_id'], $passAuth->getNewSalt(), $passAuth->getNewAlgo(), FALSE);
						}
						break;
					case '1':
						// New Password equal old password
						$defender->stop();
						$defender->addError('user_password');
						$defender->addError('user_new_password');
						$defender->addNotice($locale['u134'].$locale['u146'].$locale['u133'].".");
						break;
					case '2':
						// The two new passwords are not identical
						$defender->stop();
						$defender->addError('user_new_password');
						$defender->addError('user_new_password2');
						$defender->addHelperText('user_password', $locale['u148']);
						$defender->addNotice($locale['u148']);
						break;
					case '3':
						// New password contains invalid chars / symbols
						$defender->stop();
						$defender->addError('user_new_password');
						$defender->addHelperText('user_password', $locale['u134'].$locale['u142']."<br />".$locale['u147']);
						$defender->addNotice($locale['u134'].$locale['u142']."<br />".$locale['u147']);
						break;
				}
				} else {
					$defender->stop();
					$defender->addError('user_password');
					$defender->addHelperText('user_password', $locale['u149']);
					$defender->addNotice($locale['u149']);
				}
			}
		}
	}


	private function _setAdminPassword() {
		global $locale, $defender;
		if ($this->_getPasswordInput("user_admin_password")) { // if submit current admin password
			$this->_userAdminPassword = $this->_getPasswordInput("user_admin_password");
			$this->_newUserAdminPassword = $this->_getPasswordInput("user_admin_password");
			$this->_newUserAdminPassword2 = $this->_getPasswordInput("user_admin_password2");
			// now this is where it is different
			$passAuth = new PasswordAuth();
			if (!$this->userData['user_admin_password'] && !$this->userData['user_admin_salt']) {
				// New Admin
				$valid_current_password = 1;
				$passAuth->inputPassword = 'fake';
				$passAuth->inputNewPassword = $this->_userAdminPassword;
				$passAuth->inputNewPassword2 = $this->_newUserAdminPassword2;
			} else {
				// Old Admin
				// Intialize password auth
				$passAuth->inputPassword = $this->_userAdminPassword;
				$passAuth->inputNewPassword = $this->_newUserAdminPassword;
				$passAuth->inputNewPassword2 = $this->_newUserAdminPassword2;
				$passAuth->currentPasswordHash = $this->userData['user_admin_password'];
				$passAuth->currentAlgo = $this->userData['user_admin_algo'];
				$passAuth->currentSalt = $this->userData['user_admin_salt'];
				$valid_current_password = $passAuth->isValidCurrentPassword();
			}

			if ($valid_current_password) {
				$this->_isValidCurrentAdminPassword  = 1;
				// authenticated. now do the integrity check
				$_isValidNewPassword = $passAuth->isValidNewPassword();
				switch($_isValidNewPassword) {
					case '0':
						echo 'i am here';
						// New password is valid
						$new_admin_password = $passAuth->getNewHash();
						$new_admin_salt = $passAuth->getNewSalt();
						$new_admin_algo = $passAuth->getNewAlgo();
						$this->data['user_admin_algo'] = $new_admin_algo;
						$this->data['user_admin_salt'] = $new_admin_salt;
						$this->data['user_admin_password'] = $new_admin_password;
						break;
					case '1':
						// new password is old password
						$defender->stop();
						$defender->addError('user_admin_password');
						$defender->addError('user_admin_password1');
						$defender->addHelperText('user_admin_password', $locale['u144'].$locale['u146'].$locale['u133']);
						$defender->addHelperText('user_admin_password1', $locale['u144'].$locale['u146'].$locale['u133']);
						$defender->addNotice($locale['u144'].$locale['u146'].$locale['u133']);
						break;
					case '2':
						// The two new passwords are not identical
						$defender->stop();
						$defender->addError('user_new_admin_password');
						$defender->addError('user_new_admin_password2');
						$defender->addHelperText('user_new_admin_password', $locale['u148a']);
						$defender->addHelperText('user_new_admin_password2', $locale['u148a']);
						$defender->addNotice($locale['u144'].$locale['u148a']);
						break;
					case '3':
						// New password contains invalid chars / symbols
						$defender->stop();
						$defender->addError('user_new_admin_password');
						$defender->addHelperText('user_new_admin_password', $locale['u144']);
						$defender->addNotice($locale['u144'].$locale['u142']."<br />".$locale['u147']);
						break;
				}
			} else {
				// 149 for admin
				$defender->stop();
				$defender->addError('user_admin_password');
				$defender->addHelperText('user_admin_password', $locale['u149a']);
				$defender->addNotice($locale['u149a']);
			}
		} else { // check db only - admin cannot save profile page without password
			$valid = $this->userData['user_admin_password'] ? TRUE : FALSE;
			if (!$valid) {
				// 149 for admin
				$defender->stop();
				$defender->addError('user_admin_password');
				$defender->addHelperText('user_admin_password', $locale['u149a']);
				$defender->addNotice($locale['u149a']);
			}
		}
	}

	// Set New User Email
	private function _setUserEmail() {
		global $locale, $settings, $defender;
		$this->_userEmail = (isset($_POST['user_email']) ? stripinput(trim(preg_replace("/ +/i", " ", $_POST['user_email']))) : "");
		if ($this->_userEmail != "" && $this->_userEmail != $this->userData['user_email']) {
			// Require user password for email change
			if ($this->_isValidCurrentPassword) {
				// Require a valid email account
				if (preg_check("/^[-0-9A-Z_\.]{1,50}@([-0-9A-Z_\.]+\.){1,50}([0-9A-Z]){2,6}$/i", $this->_userEmail)) {
					$email_domain = substr(strrchr($this->_userEmail, "@"), 1);
					if (dbcount("(blacklist_id)", DB_BLACKLIST, "blacklist_email='".$this->_userEmail."' OR blacklist_email='".$email_domain."'") != 0) {
						// this email blacklisted.
						$defender->stop();
						$defender->addError('user_email');
						$defender->addHelperText('user_email', $locale['u124']);
						$defender->addNotice($locale['u124']);
					} else {
						$email_active = dbcount("(user_id)", DB_USERS, "user_email='".$this->_userEmail."'");
						$email_inactive = dbcount("(user_code)", DB_NEW_USERS, "user_email='".$this->_userEmail."'");
						if ($email_active == 0 && $email_inactive == 0) {
							if ($this->verifyNewEmail && $settings['email_verification'] == "1") {
								$this->_verifyNewEmail();
							} else {
								$this->_userLogFields[] = "user_email";
								$this->data['user_email'] = $this->_userEmail;
							}
						} else {
							// email taken
							$defender->stop();
							$defender->addError('user_email');
							$defender->addHelperText('user_email', $locale['u125']);
							$defender->addNotice($locale['u125']);
						}
					}
				} else {
					// invalid email address
					$defender->stop();
					$defender->addError('user_email');
					$defender->addHelperText('user_email', $locale['u123']);
					$defender->addNotice($locale['u123']);
				}
			} else {
				// must have a valid password to change email
				$defender->stop();
				$defender->addError('user_email');
				$defender->addHelperText('user_email', $locale['u156']);
				$defender->addNotice($locale['u156']);
			}
		} else {
			if ($this->_method !== 'validate_update') { // for register only
				$defender->stop();
				$defender->addError('user_email');
				$defender->addHelperText('user_email', $locale['u126']);
				$defender->addNotice($locale['u126']);
			}
		}
	}

	// Send Verification code when you change email
	private function _verifyNewEmail() {
		global $locale, $settings, $userdata;
		require_once INCLUDES."sendmail_include.php";
		mt_srand((double)microtime()*1000000);
		$salt = "";
		for ($i = 0; $i <= 10; $i++) {
			$salt .= chr(rand(97, 122));
		}
		$user_code = md5($this->_userEmail.$salt);
		$email_verify_link = $settings['siteurl']."edit_profile.php?code=".$user_code;
		$mailbody = str_replace("[EMAIL_VERIFY_LINK]", $email_verify_link, $locale['u203']);
		$mailbody = str_replace("[USER_NAME]", $userdata['user_name'], $mailbody);
		sendemail($this->_userName, $this->_userEmail, $settings['siteusername'], $settings['siteemail'], $locale['u202'], $mailbody);
		$result = dbquery("DELETE FROM ".DB_EMAIL_VERIFY." WHERE user_id='".$this->userData['user_id']."'");
		$result = dbquery("INSERT INTO ".DB_EMAIL_VERIFY." (user_id, user_code, user_email, user_datestamp) VALUES('".$this->userData['user_id']."', '$user_code', '".$this->_userEmail."', '".time()."')");
	}

	// Captcha validation
	private function _setValidationError() {
		global $locale, $settings, $defender;
		$_CAPTCHA_IS_VALID = FALSE;
		include INCLUDES."captchas/".$settings['captcha']."/captcha_check.php";
		if ($_CAPTCHA_IS_VALID == FALSE) {
			$defender->stop();
			$defender->addError('user_captcha');
			$defender->addHelperText('user_captcha', $locale['u194']);
			$defender->addNotice($locale['u194']);
		}
	}

	// Change Avatar, Drop Avatar, New Avatar Upload
	private function _setUserAvatar() {
		global $locale, $settings, $defender;
		if (isset($_POST['delAvatar'])) {
			if ($this->userData['user_avatar'] != "" && file_exists(IMAGES."avatars/".$this->userData['user_avatar']) && is_file(IMAGES."avatars/".$this->userData['user_avatar'])) {
				unlink(IMAGES."avatars/".$this->userData['user_avatar']);
			}
			$this->data['user_avatar'] = '';
		}
		if (isset($_FILES['user_avatar']) && $_FILES['user_avatar']['name']) { // uploaded avatar
			require_once INCLUDES."infusions_include.php";
			$source_name = 'user_avatar';
			$target_name = '';
			$target_folder = IMAGES.'avatars/';
			$target_width = 2000;
			$target_height = 2000;
			$max_size = $settings['avatar_filesize'];
			$delete_original = TRUE;
			$create_thumb1 = TRUE;
			$create_thumb2 = FALSE;
			$ratio = $settings['avatar_ratio'];
			$thumb1_suffix = "[".$this->userData['user_id']."]";
			$thumb1_height = $settings['avatar_height'];
			$thumb1_width = $settings['avatar_width'];
			$avatarUpload = upload_image($source_name, $target_name, $target_folder, $target_width, $target_height, $max_size, $delete_original, $create_thumb1, $create_thumb2, $ratio, $target_folder, $thumb1_suffix, $thumb1_width, $thumb1_height);
			if ($avatarUpload['error'] == 0) {
				if ($this->userData['user_avatar'] && $this->userData['user_avatar'] !== $avatarUpload['thumb1_name'] && file_exists(IMAGES."avatars/".$this->userData['user_avatar']) && is_file(IMAGES."avatars/".$this->userData['user_avatar'])) {
					unlink(IMAGES."avatars/".$this->userData['user_avatar']);
				}
				$this->data['user_avatar'] = $avatarUpload['thumb1_name'];
			} else {
				$defender->stop();
				$defender->addError('user_avatar');
				switch($avatarUpload['error']) {
					case 1:
						$defender->addHelperText('user_avatar', sprintf($locale['u180'], parsebytesize($settings['avatar_filesize'])));
						$defender->addNotice($locale['u180']);
						break;
					case 2:
						$defender->addHelperText('user_avatar', $locale['u181']);
						$defender->addNotice($locale['u181']);
						break;
					case 3:
						$defender->addHelperText('user_avatar',  sprintf($locale['u182'], $settings['avatar_width'], $settings['avatar_height']));
						$defender->addNotice($locale['u182']);
						break;
					case 4:
						$defender->addHelperText('user_avatar', $locale['u183']);
						$defender->addNotice($locale['u183']);
						break;
					case 5:
						$defender->addHelperText('user_avatar', $locale['u183']);
						$defender->addNotice($locale['u183']);
						break;
					default:
						$defender->addHelperText('user_avatar', $locale['u183']);
						$defender->addNotice($locale['u183']);
						break;
				}
			}
		}
	}

	private function _setEmptyFields() {
		$this->_userHideEmail = isset($_POST['user_hide_email']) && $_POST['user_hide_email'] == 1 ? 1 : 0;
		$userStatus = $this->adminActivation == 1 ? 2 : 0;
		if ($this->_method == "validate_insert") {
			$this->data['user_hide_email'] = $this->_userHideEmail;
			$this->data['user_avatar'] = '';
			$this->data['user_posts'] = 0;
			$this->data['user_threads'] = 0;
			$this->data['user_joined'] = time();
			$this->data['user_lastvisit'] = 0;
			$this->data['user_ip'] = USER_IP;
			$this->data['user_ip_type'] = USER_IP_TYPE;
			$this->data['user_rights'] = '';
			$this->data['user_groups'] = '';
			$this->data['user_level'] = 101;
			$this->data['user_status'] = $userStatus;
			$this->data['user_timezone'] = fusion_get_settings('timeoffset');
			$this->data['user_theme'] = 'Default';
			$this->data['user_language'] = LANGUAGE;
		} else {
			$this->data['user_theme'] = (isset($_POST['user_theme'])) ? $_POST['user_theme'] : 'Default';
			$this->data['user_timezone'] = (isset($_POST['user_timezone'])) ? $_POST['user_timezone'] : fusion_get_settings('timeoffset');
			$this->data['user_hide_email'] = $this->_userHideEmail;
		}
	}

	// Get Password Input - if empty return false
	private function _getPasswordInput($field) {
		return isset($_POST[$field]) && $_POST[$field] != "" ? $_POST[$field] : FALSE;
	}

	private function _setEmailVerification() {
		global $settings, $locale, $defender;
		require_once INCLUDES."sendmail_include.php";
		$userCode = hash_hmac("sha1", PasswordAuth::getNewPassword(), $this->_userEmail);
		$activationUrl = $settings['siteurl']."register.php?email=".$this->_userEmail."&code=".$userCode;
		$message = str_replace("USER_NAME", $this->_userName, $locale['u152']);
		$message = str_replace("USER_PASSWORD", $this->_newUserPassword, $message);
		$message = str_replace("ACTIVATION_LINK", $activationUrl, $message);
		if (sendemail($this->_userName, $this->_userEmail, $settings['siteusername'], $settings['siteemail'], $locale['u151'], $message)) {
			$quantum = new QuantumFields();
			$quantum->category_db = DB_USER_FIELD_CATS;
			$quantum->field_db = DB_USER_FIELDS;
			$quantum->plugin_folder = INCLUDES."user_fields/";
			$quantum->plugin_locale_folder = LOCALE.LOCALESET."user_fields/";
			$quantum->load_data();
			$userInfo = $this->data;
			if ($quantum->output_fields(DB_USERS)) $userInfo += $quantum->output_fields(DB_USERS);
			$userInfo += $quantum->output_fields(DB_USERS);
			$userInfo = serialize($userInfo);
			$userInfo = addslash($userInfo);
			$result = dbquery("INSERT INTO ".DB_NEW_USERS."
					(user_code, user_name, user_email, user_datestamp, user_info)
					VALUES
					('".$userCode."', '".$this->data['user_name']."', '".$this->data['user_email']."', '".time()."', '".$userInfo."'
					)");
			$this->_completeMessage = $locale['u150'];
		} else {
			$defender->stop();
			$defender->setNoticeTitle($locale['u165']);
			$defender->addNotice($locale['u153']."<br />".$locale['u154']);
		}
	}

	private function _setUserDataInput() {
		global $locale, $settings, $userdata, $aidlink;
		$quantum = new QuantumFields();
		$quantum->category_db = DB_USER_FIELD_CATS;
		$quantum->field_db = DB_USER_FIELDS;
		$quantum->plugin_folder = INCLUDES."user_fields/";
		$quantum->plugin_locale_folder = LOCALE.LOCALESET."user_fields/";
		$quantum->load_data();
		dbquery_insert(DB_USERS, $this->data, 'save', array('keep_session'=>1));
		$this->data['user_id'] = dblastid();
		$quantum->quantum_insert($this->data);
		if ($this->adminActivation) {
			$this->_completeMessage = $locale['u160']."<br /><br />\n".$locale['u162'];
		} else {
			if (!defined('ADMIN_PANEL')) {
				$this->_completeMessage = $locale['u160']."<br /><br />\n".$locale['u161'];
			} else {
				require_once LOCALE.LOCALESET."admin/members_email.php";
				require_once INCLUDES."sendmail_include.php";
				$subject = $locale['email_create_subject'].$settings['sitename'];
				$replace_this = array("[USER_NAME]", "[PASSWORD]");
				$replace_with = array($this->_userName, $this->_newUserPassword);
				$message = str_replace($replace_this, $replace_with, $locale['email_create_message']);
				sendemail($this->_userName, $this->_userEmail, $settings['siteusername'], $settings['siteemail'], $subject, $message);
				$this->_completeMessage = $locale['u172']."<br /><br />\n<a href='members.php".$aidlink."'>".$locale['u173']."</a>";
				$this->_completeMessage .= "<br /><br /><a href='members.php".$aidlink."&amp;step=add'>".$locale['u174']."</a>";
			}
		}
	}

	private function _setUserDataUpdate() {
		global $locale;
		$this->_saveUserLog();
		$quantum = new QuantumFields();
		$quantum->category_db = DB_USER_FIELD_CATS;
		$quantum->field_db = DB_USER_FIELDS;
		$quantum->plugin_folder = INCLUDES."user_fields/";
		$quantum->plugin_locale_folder = LOCALE.LOCALESET."user_fields/";
		$quantum->input_page = isset($_GET['profiles']) && isnum($_GET['profiles']) ? $_GET['profiles'] : 1;
		$quantum->load_data();
		if ($quantum->input_page == 1) {
			dbquery_insert(DB_USERS, $this->data, 'update', array('keep_session'=>1));
		}
		// only will save on UFs.
		$quantum->quantum_insert($this->data); // update database
		$this->_completeMessage = $locale['u163'];
	}



	private function _saveUserLog() {
		$i = 0;
		$sql = "";
		foreach ($this->_userLogData AS $field => $value) {
			if ($this->userData[$field] != $value) {
				if ($i == 0) {
					$sql = "INSERT INTO ".DB_USER_LOG." (userlog_user_id, userlog_field, userlog_value_new, userlog_value_old, userlog_timestamp) VALUES ";
				}
				$sql .= ($i > 0 ? ", " : "")."('".$this->userData[$field]."', '".$field."', '".$value."', '".$this->userData[$field]."', '".time()."')";
				$i++;
			}
		}
		if ($sql != "") {
			$result = dbquery($sql);
		}
	}
}
