<?php
	//error_reporting(0);
	require_once("php-form-validation.php");
	$error = '';
	$chk = 'error';
	global $wpdb;
	global $current_user;
	get_currentuserinfo();
	
	if(is_user_logged_in())
	{
		$sponsor_name = $current_user->user_login;
		$readonly_sponsor = 'readonly';
	
	}else if(isset($_REQUEST['sp']) &&  $_REQUEST['sp'] != ''){
		
		$sponsorName = getUsernameByKey($_REQUEST['sp']); 			
		if(isset($sponsorName) && $sponsorName !='' )		
		{
			$readonly_sponsor = 'readonly';
			$sponsor_name = $sponsorName;
		}else{
			
			redirectPage(home_url(), array()); exit; 

		}
		
	}else{
		$readonly_sponsor = '';
	}
	
	//most outer if condition
	if(isset($_POST['submit']))
	{
		$firstname = sanitize_text_field( $_POST['firstname'] );
		$lastname = sanitize_text_field( $_POST['lastname'] );
		$username = sanitize_text_field( $_POST['username'] );
		$password = sanitize_text_field( $_POST['password'] );
		$confirm_pass = sanitize_text_field( $_POST['confirm_password'] );
		$email = sanitize_text_field( $_POST['email'] );
		$confirm_email = sanitize_text_field( $_POST['confirm_email'] );
		$address1 = sanitize_text_field( $_POST['address1'] );
		$address2 = sanitize_text_field( $_POST['address2'] );
		$sponsor = sanitize_text_field( $_POST['sponsor'] );
		$city = sanitize_text_field( $_POST['city'] );
		$state = sanitize_text_field( $_POST['state'] );
		$postalcode = sanitize_text_field( $_POST['postalcode'] );
		$telephone = sanitize_text_field( $_POST['telephone'] );
		$dob = sanitize_text_field( $_POST['dob'] );
		
		
		//Add usernames we don't want used
		$invalid_usernames = array( 'admin' );
		//Do username validation
		$username = sanitize_user( $username );
		
		if(!validate_username($username) || in_array($username, $invalid_usernames)) 
			$error .= "\n Username is invalid.";
			
		if ( username_exists( $username ) ) 
			$error .= "\n Username already exists.";
		
		if ( checkInputField($password) ) 
			$error .= "\n Please enter your password.";
			
		if ( confirmPassword($password, $confirm_pass) ) 
			$error .= "\n Please confirm your password.";
		
		if ( checkInputField($sponsor) ) 
			$error .= "\n Please enter your sponsor name.";
		
		if ( !checkValidSponsor($sponsor) ) {	
			$error .= "\n Sponsor Name is invalid.";
		}
		
		if ( checkInputField($firstname) ) 
			$error .= "\n Please enter your first name.";
			
		if ( checkInputField($lastname) ) 
			$error .= "\n Please enter your last name.";
					
		if ( checkInputField($address1) ) 
			$error .= "\n Please enter your address.";
			
		if ( checkInputField($city) ) 
			$error .= "\n Please enter your city.";
			
		if ( checkInputField($state) ) 
			$error .= "\n Please enter your state.";
			
		if ( checkInputField($postalcode) ) 
			$error .= "\n Please enter your postal code.";
			
		if ( checkInputField($telephone) ) 
			$error .= "\n Please enter your contact number.";

		if ( checkInputField($dob) ) 
			$error .= "\n Please enter your date of birth.";
		
		//Do e-mail address validation
		if ( !is_email( $email ) )
			$error .= "\n E-mail address is invalid.";
			
		if (email_exists($email))
			$error .= "\n E-mail address is already in use.";
		
		if ( confirmEmail($email, $confirm_email) ) 
			$error .= "\n Please confirm your email address.";
		
				
		if(isset($_GET['l'])&&$_GET['l']!='')
			$leg = $_GET['l'];
		else
			$leg = $_POST['leg'];
			
		if($leg!='0')
		{
			if($leg!='1')
			{
				$error .= "\n You have enter a wrong placement.";
			}
		}
		//generate random numeric key for new user registration
		$user_key = generateKey();
		//if generated key is already exist in the DB then again re-generate key
		do
		{
			$check = mysql_fetch_array(mysql_query("SELECT COUNT(*) ck 
													FROM ".WPMLM_TABLE_USER." 
													WHERE `user_key` = '".$user_key."'"));
			$flag = 1;
			if($check['ck']==1)
			{
				$user_key = generateKey();
				$flag = 0;
			}
		}while($flag==0);
		
		//check parent key exist or not
		if(isset($_GET['k'])&&$_GET['k']!='')
		{
			if(!checkKey($_GET['k']))
				$error .= "\n Parent key does't exist.";
			// check if the user can be added at the current position
			$checkallow = checkallowed($_GET['k'],$leg);
			if($checkallow >=1)
				$error .= "\n You have enter a wrong placement.";
		}
		// outer if condition
		if(empty($error))
		{
			// inner if condition
			
			$sponsor = getSponsorKeyBySponsorname($_REQUEST['sponsor']);
			
			
			if($sponsor!=''){						
				
				
				//find parent key
				if(isset($_GET['k'])&&$_GET['k']!='')
				{
					$p_key = $_GET['k'];
					if(checkValidParentKey($p_key))
					{
						$parent_key = $p_key; 
				
					}else{
						$error = "Invalid Parent Key";
					
					}
					
					
				}else{
					
					$sponsor_key =  $sponsor;				
					do
					{
		
						$parentquery = mysql_query("SELECT `user_key` FROM ".WPMLM_TABLE_USER." 
													WHERE parent_key = '".$sponsor_key."' AND 
													leg = '".$leg."' AND banned = '0'");
						
													
						$num = mysql_num_rows($parentquery);
						if($num)
						{
							$ref1 = mysql_fetch_array($parentquery);
							$sponsor_key = $ref1['user_key'];
						}
		
					}while($num==1);
					$parent_key = $sponsor_key ;
				
				} 
								
				
				$user = array
					(
						'user_login' => $username,
						'user_pass' => $password,
						'first_name' => $firstname,
						'last_name' => $lastname,
						'user_email' => $email
					);
					
				// return the wp_users table inserted user's ID
				$user_id = wp_insert_user($user);
				
				//get the selected country name from the country table
				$country = $_POST['country'];
				$sql = "SELECT country 
						FROM ".WPMLM_TABLE_COUNTRY."
						WHERE id = '".$country."'";
				$sql = mysql_query($sql);
				$country1 = mysql_fetch_object($sql);
				$unique=TRUE;
				//insert the registration form data into user_meta table
				add_user_meta( $user_id, 'user_address1', $address1, $unique );
				add_user_meta( $user_id, 'user_address2', $address2, $unique );
				add_user_meta( $user_id, 'user_city', $city, $unique );
				add_user_meta( $user_id, 'user_state', $state, $unique );
				add_user_meta( $user_id, 'user_country', $country1->country, $unique );
				add_user_meta( $user_id, 'user_postalcode', $postalcode, $unique );
				add_user_meta( $user_id, 'user_telephone', $telephone, $unique );
				add_user_meta( $user_id, 'user_dob', $dob, $unique );
				
				/*Send e-mail to admin and new user - 
				You could create your own e-mail instead of using this function*/
				wp_new_user_notification($user_id, $password);
				
				//insert the data into fa_user table
				$insert = "INSERT INTO ".WPMLM_TABLE_USER."
						   (
								user_id, user_key, parent_key, sponsor_key, leg, 
								payment_status, banned,qualification_pv, left_pv,right_pv,own_pv,
								create_date,paid_date
							) 
							VALUES
							(
								'".$user_id."','".$user_key."', '".$parent_key."', '".$sponsor."', '".$leg."',
								'0','0','0','0','0','0','".date('Y-m-d H:i:s')."',''
								
							)";
										
								
				// if all data successfully inserted
				if(mysql_query($insert))
				{	//begin most inner if condition
					
					//entry on left leg and Right leg
					if($leg==0)
					{
						mysql_query("INSERT INTO `".WPMLM_TABLE_LEFT_LEG."` 
							(`id`, `pkey`,`ukey`) 
								VALUES 
								('', '".$parent_key."','".$user_key."')");
					}
					else if($leg==1)
					{
						mysql_query("INSERT INTO `".WPMLM_TABLE_RIGHT_LEG."` 
									(`id`, `pkey`,`ukey`)
									VALUES 
									 ('', '".$parent_key."','".$user_key."')");
					}
			
				   while($parent_key!='0')
				   {
						$query = mysql_query("SELECT `parent_key`, `leg` FROM ".WPMLM_TABLE_USER." WHERE `user_key` = '".$parent_key."'");
						$num_rows = mysql_num_rows($query);
						if($num_rows)
						{
							$result = mysql_fetch_array($query);
							if($result['parent_key']!='0')
							{
								if($result['leg']==1)
								{
									mysql_query("INSERT INTO `".WPMLM_TABLE_RIGHT_LEG."` (`id`, `pkey`,`ukey`) 
									VALUES ('','".$result['parent_key']."','".$user_key."')");
								}
								else
								{
									mysql_query("INSERT INTO `".WPMLM_TABLE_LEFT_LEG."` (`id`, `pkey`,`ukey`) 
									VALUES ('','".$result['parent_key']."','".$user_key."')");
								}
							}
							$parent_key = $result['parent_key'];
						}
						else
						{
							$parent_key = '0';
						}
				   }
					
					
					$chk = '';
					$msg= "<span style='color:green;'>Congratulations! You have successfully registered.</span><br /><br />
						<p>Your Member Id is : ".$user_key."</p>
						<p style='font-size:18px; font-weight:bold;'><a href='".wp_login_url()."'>
						Click here to continue to login</a></p>";
				}//end most inner if condition
			}else{
			
				$error = "Invalid Sponsor";
			}
			
		}//end outer if condition
	}//end most outer if condition
	
	//if any error occoured
	if(!empty($error))
		$error = nl2br($error);
		
	if($chk!='')
	{
?>
<script src="<?= WPMLM_URL ?>/wpmlm-admin/js/jquery-1.8.0.js"></script>
<script type="text/javascript" src="<?= WPMLM_URL.'/js/form-validation.js'?>"></script>
<script type="text/javascript" src="<?= WPMLM_URL.'/js/epoch_classes.js'?>"></script> 
<link rel="stylesheet" type="text/css" href="<?= WPMLM_URL.'/css/epoch_styles.css'?>"/> 
<script type="text/javascript">
var popup1,popup2,splofferpopup1;
var bas_cal, dp_cal1,dp_cal2, ms_cal; // declare the calendars as global variables 
window.onload = function() {
	dp_cal1 = new Epoch('dp_cal1','popup',document.getElementById('dob'));  
};

function checkUserNameAvailability(str)
{
	if(isSpclChar(str,'username')==false)
	{
		document.getElementById('username').focus();
		return false;
	}
	var xmlhttp;    
	if (str=="")
  	{
  		alert("Please enter the user name.");
		document.getElementById('username').focus();
		return false;
  	}
	
	$("#check_user").html('<img src="<?= WPMLM_URL ?>/images/indicator.gif"> Please wait...');
	jQuery.ajax({
		type: "POST",
		url: "<?= WPMLM_URL ?>/wpmlm-core/ajax-functions.php",
		data: "action=username&q="+str,
		success: function(msg){
			$("#check_user").html(msg);
		}
	});
}

function checkReferrerAvailability(str)
{
	if(isSpclChar(str, 'sponsor')==false)
	{
		document.getElementById('sponsor').focus();
		return false;
	}
	var xmlhttp;    
	if (str=="")
  	{
  		alert("Please enter the sponsor name.");
		document.getElementById('sponsor').focus();
		return false;
  	}
	
	$("#check_referrer").html('<img src="<?= WPMLM_URL ?>/images/indicator.gif"> Please wait...');
	jQuery.ajax({
		type: "POST",
		url: "<?= WPMLM_URL ?>/wpmlm-core/ajax-functions.php",
		data: "action=sponsor&q="+str,
		success: function(msg){
			$("#check_referrer").html(msg);
		}
	});
	

}


</script>
<span style='color:red;'><?=$error?></span>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
	<form name="frm" method="post" action="" onSubmit="return formValidation();">
		<tr>
			<td>Create Username <span style="color:red;">*</span> :</td>
			<td><input type="text" name="username" id="username" value="<?= htmlentities(isset($_POST['username'])&&$_POST['username']);?>" maxlength="20" size="37" onBlur="checkUserNameAvailability(this.value);"><br /><div id="check_user"></div></td>
		</tr>
		
		<tr>
			<td>Create Password <span style="color:red;">*</span> :</td>
			<td>	<input type="password" name="password" id="password" maxlength="20" size="37" >
				<br /><span style="font-size:12px; font-style:italic; color:#006633">Password length atleast 6 character</span>
			</td>
		</tr>
				
		<tr>
			<td>Confirm Password <span style="color:red;">*</span> :</td>
			<td><input type="password" name="confirm_password" id="confirm_password" maxlength="20" size="37" ></td>
		</tr>
				
		<tr>
			<?php
			if(isset($sponsor_name) && $sponsor_name!='')
			{
				
				$spon = $sponsor_name;
			}
			else if(isset($_POST['sponsor']))
				$spon = htmlentities($_POST['sponsor']);
				else
				$spon='';
			?>
			<td>Sponsor Id <span style="color:red;">*</span> :</td>
			<td>
			<input type="text" name="sponsor" id="sponsor" value="<?= $spon;?>" maxlength="20" size="37" onBlur="checkReferrerAvailability(this.value);" <?= $readonly_sponsor;?>>
			<br /><div id="check_referrer"></div>
			</td>
		</tr>
		
	
		
		<tr>
			<td>Placement <span style="color:red;">*</span> :</td>
			<?php
					if(isset($_POST['leg'])&&$_POST['leg']=='0')
						$checked = 'checked';
					else if(isset($_GET['l'])&& $_GET['l']=='0')
					{
						$checked = 'checked';
						$disable_leg = 'disabled';
					}
					else
						$checked = '';
					if(isset($_POST['leg'])&& $_POST['leg']=='1')
						$checked1 = 'checked';
					else if(isset($_GET['l'])&& $_GET['l']=='1')
					{
						$checked1 = 'checked';
						$disable_leg = 'disabled';
					}
					else
						$checked1 = '';
										
			?>
			<td>Left <input id="left" type="radio" name="leg" value="0" <?= $checked;?> <?= $disable_leg;?>/>Right<input id="right" type="radio" name="leg" value="1" <?= $checked1;?> <?= $disable_leg;?>/>
			</td>
		</tr>
		
	
		
		<tr>
			<td>First Name <span style="color:red;">*</span> :</td>
			<td><input type="text" name="firstname" id="firstname" value="<?= htmlentities(isset($_POST['firstname'])&&$_POST['firstname']);?>" maxlength="20" size="37" onBlur="return checkname(this.value, 'firstname');" ></td>
		</tr>
		
	
		
		<tr>
			<td>Last Name <span style="color:red;">*</span> :</td>
			<td><input type="text" name="lastname" id="lastname" value="<?= htmlentities(isset($_POST['lastname'])&&$_POST['lastname']);?>" maxlength="20" size="37" onBlur="return checkname(this.value, 'lastname');"></td>
		</tr>
		
	
		
		<tr>
			<td>Address Line 1 <span style="color:red;">*</span> :</td>
			<td><input type="text" name="address1" id="address1" value="<?= htmlentities(isset($_POST['address1'])&&$_POST['address1']);?>"  size="37" onBlur="return allowspace(this.value,'address1');"></td>
		</tr>
		

		
		<tr>
			<td>Address Line 2 :</td>
			<td><input type="text" name="address2" id="address2" value="<?= htmlentities(isset($_POST['address2'])&&$_POST['address2']);?>"  size="37" onBlur="return allowspace(this.value,'address2');"></td>
		</tr>
		

		
		<tr>
			<td>City <span style="color:red;">*</span> :</td>
			<td><input type="text" name="city" id="city" value="<?= htmlentities(isset($_POST['city'])&& $_POST['city']);?>" maxlength="30" size="37" onBlur="return allowspace(this.value,'city');"></td>
		</tr>
		
		
		<tr>
			<td>State <span style="color:red;">*</span> :</td>
			<td><input type="text" name="state" id="state" value="<?= htmlentities(isset($_POST['state'])&& $_POST['state']);?>" maxlength="30" size="37" onBlur="return allowspace(this.value,'state');"></td>
		</tr>
		

		
		<tr>
			<td>Postal Code <span style="color:red;">*</span> :</td>
			<td><input type="text" name="postalcode" id="postalcode" value="<?= htmlentities(isset($_POST['postalcode'])&&$_POST['postalcode']);?>" maxlength="20" size="37" onBlur="return allowspace(this.value,'postalcode');"></td>
		</tr>
		

		
		<tr>
			<td>Country <span style="color:red;">*</span> :</td>
			<td>
				<?php
					$sql = "SELECT id, country
							FROM ".WPMLM_TABLE_COUNTRY."
							ORDER BY country";		
					$sql = mysql_query($sql);
				?>
				<select name="country" id="country" >
					<option value="">Select Country</option>
				<?php
					while($row = mysql_fetch_object($sql))
					{
						if($_POST['country']==$row->id)
							$selected = 'selected';
						else
							$selected = '';
				?>
						<option value="<?= $row->id;?>" <?= $selected?>><?= $row->country;?></option>
				<?php
					}
				?>
				</select>
			</td>
		</tr>

		
		<tr>
			<td>Email Address <span style="color:red;">*</span> :</td>
			<td><input type="text" name="email" id="email" value="<?= htmlentities(isset($_POST['email'])&&$_POST['email']);?>"  size="37" ></td>
		</tr>

		
		<tr>
			<td>Confirm Email Address <span style="color:red;">*</span> :</td>
			<td><input type="text" name="confirm_email" id="confirm_email" value="<?= htmlentities(isset($_POST['confirm_email'])&& $_POST['confirm_email']);?>" size="37" ></td>
		</tr>
		
		
		<tr>
			<td>Contact No. <span style="color:red;">*</span> :</td>
			<td><input type="text" name="telephone" id="telephone" value="<?= htmlentities(isset($_POST['telephone'])&&$_POST['telephone']);?>" maxlength="20" size="37" onBlur="return numeric(this.value, 'telephone');" ></td>
		</tr>
		
		
		<tr>
			<td>Date of Birth <span style="color:red;">*</span> :</td>
			<td><input type="text" name="dob" id="dob" value="<?= htmlentities(isset($_POST['dob']) && $_POST['dob']);?>" maxlength="20" size="22" ></td>
		</tr>
	
		
		<tr>
			<td colspan="2"><input type="submit" name="submit" id="submit" value="Submit" /></td>
		</tr>
	</form>
</table>
<?php
	}
	else
		echo $msg;


?>