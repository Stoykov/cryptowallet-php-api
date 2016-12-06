<?php
namespace Stoykov\CryptoWallet;
/*
	Original source from: Shane B. (Xenland)
	Modified by: Antoan Stoykov
	
	Date: Sep, 2014
	
	Purpose: To provide a drop-in library for php programmers that are not educated in the art of financial security and programming methods.
	Last Updated in Version: 0.0.x
	Donation Bitcoin Address (Shane B.): 13ow3MfnbksrSxdcmZZvkhtv4mudsnQeLh
	Donation Reddcoin Address (Antoan Stoykov): Ro9D17Q9E3vrSPZxKt5gePSE9dyCeqkkk2
	Website: http://alienshaped.com http://bitcoindevkit.com
	
	License (AGPL)
*/

class Crypto_API
{	
	/**
	* @var string
	*/
	protected $integrity_check = '';
	
	/**
	* @var array
	*/
	protected $server = array();
	
	/**
	* @var array
	*/
	protected $settings = array();
	
	/**
	* @var object
	*/
	protected $connection;

	public function __construct($integrity, $settings, $server)
	{
		$this->integrity_check	= $integrity;
		$this->settings			= $settings;
		$this->server			= $server;
	}
	
	/**
	* @method open_connection
	* @desc Opens a connection to server
	*
	* @return bool
	*/
	public function open_connection()
	{
		$return_status = false;
		$connection = null;

		if($this->server["host"] != '' && $this->server["user"] != '' && $this->server["pass"] != '' && $this->server["https"] != '' && $this->server["port"] > 0)
		{
			try
			{
				$connection = new jsonRPCClient($this->server["https"].'://'.$this->server["user"].':'.$this->server["pass"].'@'.$this->server["host"].':'.$this->server["port"]);
			}
			catch(Exception $e)
			{
				$return_status = false;
				$connection = null;
			}
			
			if($connection != null && $connection != false)
			{
				$return_status = true;	
				$this->connection = $connection;
			}
		}
		
		return $return_status;
	}

	/**
	* @method set_tx_fee
	* @desc Sets transaction fees
	*
	* @param int $amount_in_satoshi
	*
	* @return bool
	*/
	public function set_tx_fee($amount_in_satoshi = 0)
	{
		$return_status = false;
		$amount_in_satoshi = floor($amount_in_satoshi);
		
		if($this->connection)
		{
			$set_tx_fee_return_status = '';
			try
			{
				$set_tx_fee_return_status = $this->connection->settxfee($amount_in_satoshi);
			}
			catch(Exception $e)
			{
				$set_tx_fee_return_status = '';
			}
			
			if($set_tx_fee_return_status == "true")
			{
				$output["return_status"] = true;
			}
			else
			{
				$return_status = false;
			}
		}
		else
		{
			$return_status = false;
		}

		return $return_status;
	}	

	/**
	* @method generate_new_address
	* @desc query server and return address
	*
	* @param string $label
	*
	* @return string
	*/
	public function generate_new_address($label = '')
	{
		$new_address = false;
		
		if($this->connection)
		{
			$tmp_new_address = '';
			try
			{
				$tmp_new_address = $this->connection->getnewaddress($label);
			}
			catch(Exception $e)
			{
				$tmp_new_address = '';
			}
			
			if($this->validate_address($tmp_new_address))
			{
				$new_address = $tmp_new_address;
			}
			else
			{
				$new_address = false;
			}
		}
		else
		{
			$new_address = false;
		}
			
		return $new_address;
	}

	/**
	* @method validate_address
	* @desc query server and detects if string is a valid address
	*
	* @param string $address
	*
	* @return array
	*/
	public function validate_address($address = '')
	{
		$output["isvalid"] 		= 0;
		$output["ismine"] 		= 0;
		$output["isscript"] 	= 0;
		$output["pubkey"] 		= '';
		$output["iscompressed"] = 0;
		$output["label"] 		= '';
		
		if($address != '')
		{
			if($this->connection)
			{
				$tmp_command_executed = 0;
				try
				{
					$tmp_valid_address = $this->connection->validateaddress($address);
					$tmp_command_executed = 1;
					
				}
				catch(Exception $e)
				{
					$tmp_command_executed = 0;
				}

				if($tmp_command_executed == 1 && $tmp_valid_address != null)
				{
					$output["return_status"] = true;
					
					if($tmp_valid_address["isvalid"] == true)
					{
						$output["return_status"] 	= true;
						$output["isvalid"] 			= true;

						if($tmp_valid_address["ismine"] == 1)
						{
							$output["ismine"] = 1;
						}
						else
						{
							$output["ismine"] = 0;
						}

						if($tmp_valid_address["isscript"] == 1)
						{
							$output["isscript"] = 1;
						}
						else
						{
							$output["isscript"] = 0;
						}

						$output["pubkey"] = $tmp_valid_address["pubkey"];

						if($tmp_valid_address["iscompressed"] == 1)
						{
							$output["iscompressed"] = 1;
						}
						else
						{
							$output["iscompressed"] = 0;
						}

						$output["label"] = strip_tags($tmp_valid_address["account"]);
					}
					else
					{
						$output["isvalid"] = false;
					}
				}
				else if($tmp_command_executed == 0)
				{
					$output["isvalid"] = false;
				}
			}
			else
			{
				$output["isvalid"] = false;
			}
		}
		else
		{
			$output["isvalid"] = false;
		}
		
		return $output;
	}

	/**
	* @method get_address_label
	* @desc query server and return the label assigned to the associated address
	*
	* @param string $address
	*
	* @return array
	*/
	public function get_address_label($address = '')
	{
		$output["return_status"]			= -1;
		$output["address_label"]			= '';
		$output["checksum"]					= '';
		$output["checksum_match"]			= -1; // -1=Unknown; 0=False; 1= Success, Checksum good
		$output["amount_due_in_satoshi"]	= 0; //Amount due (according to the label and checksum verification)
		$output["timestamp_generated"]		= 0; //Timestamp upon when the customer created the receipt (according to label and checksum verification)
		$output["products_in_receipt"]		= array();

		if($this->connection)
		{
			$tmp_label_success = 0;
			$tmp_address_label = '';
			
			try
			{
				$tmp_address_label = $this->connection->getaccount($address);
				$tmp_label_success = 1;
				
				$output["return_status"] = true;
			}
			catch(Exception $e)
			{
				$tmp_address_label = '';
				$output["return_status"] = false;
			}
			
			if($tmp_label_success == 1)
			{
				$output["address_label"] = $tmp_address_label;

				$unverified_receipt_information = json_decode($tmp_address_label, true);

				$tmp_store_checksum 						= $unverified_receipt_information["checksum"];
				$unverified_receipt_information["checksum"] = '';
				$receipt_data_checksum 						= hash($bdk_settings["hash_type"], json_encode($unverified_receipt_information));

				if($tmp_store_checksum == $receipt_data_checksum)
				{
					$unverified_receipt_information["checksum"] = $tmp_store_checksum;
					
					$output["checksum"]					= $tmp_store_checksum;
					$output["checksum_match"]			= 1;
					$output["timestamp_generated"]		= (int)$unverified_receipt_information["timestamp_generated"];
					$output["amount_due_in_satoshi"]	= (int)intval($unverified_receipt_information["amount_due_in_satoshi"]);
					$output["products_in_receipt"]		= $unverified_receipt_information["products_in_receipt"];

					$output["return_status"] = true;
				}
				else
				{
					$output["return_status"] = false;
				}	
			}
			else
			{
				$output["return_status"] = false;
			}
		}
		else
		{
			$output["return_status"] = false;
		}

		return $output;
	}

	/**
	* @method verify_message
	* @desc Query server and verify the message associated with this address and signatures.
	*
	* @param string $address
	* @param string signature
	* @param string $message
	*
	* @return bool
	*/
	public function verify_message($address = '', $signature = '', $message = '')
	{			
		$return_status = false;
		
		if($this->connection)
		{
			$tmp_verifymessage_status = '';
			$tmp_command_success = 0;
			
			try
			{
				$tmp_verifymessage_status = $this->connection->verifymessage($address, $signature, $message);
				$tmp_command_success = 1;
			}
			catch(Exception $e)
			{
				$tmp_verifymessage_status = '';
				$tmp_command_success = 0;
			}

			if($tmp_verifymessage_status == false || $tmp_verifymessage_status == true)
			{
				//Query to successfully executed
				if($tmp_verifymessage_status == true)
				{
					$output["message_valid"] = 1;
				}
				else if($tmp_verifymessage_status == false)
				{
					$output["message_valid"] = 0;
				}
				
				$return_status = true;
			}
			else
			{
				$return_status = false;
			}
		}
		else
		{
			$return_status = false;
		}
			
		return $return_status;
	}

	/**
	* @method list_transactions
	* @desc Query server and return all transactions for label
	*
	* @param string $account
	* @param int $count
	* @param int $from
	*
	* @return array
	*/
	public function list_transactions($account = '*', $count = 9999999999999, $from = 0)
	{
		$transaction_list = false;

		$account = iconv("UTF-8", "UTF-8//IGNORE", $account);
		
		$count = (int)$count;
		if($count <= 0)
		{
			$count = 1;
		}

		$from = (int)$from;
		if($from < 0)
		{
			$from = 0;
		}
		
		if($this->connection)
		{
			$transaction_list = $this->connection->listtransactions($account, $count, $from);
			if(!is_array($transaction_list))
			{
				$transaction_list = false;
			}
		}
		else
		{
			$transaction_list = false;
		}
		
		return $transaction_list;
	}

	/**
	* @method get_received_by_address
	* @desc Query server and return the total overall accumulated coins for given account
	*
	* @param string $address
	* @param int $minimum_confirmations
	*
	* @return int
	*/
	public function get_received_by_address($address = '', $minimum_confirmations = 1)
	{
		$total_received_in_satoshi = -1;

		$address		= strip_tags($address);
		$minimum_confirmations	= (int)floor($minimum_confirmations);
		
		if($minimum_confirmations <= 0)
		{
			$minimum_confirmations = 0;
		}

		if($address != '')
		{
			if($this->connection)
			{
				$tmp_is_valid_address = $this->validate_address($address);
				
				if($tmp_is_valid_address["isvalid"] == 1)
				{
					$tmp_command_executed = 0;
					try
					{
						$tmp_total_received = $this->connection->getreceivedbyaddress($address, $minimum_confirmations);
						$tmp_command_executed = 1;
					}
					catch(Exception $e)
					{
						$tmp_command_executed = 0;
					}
					
					if($tmp_command_executed == 1)
					{
						if($tmp_total_received >= 0)
						{	
							$total_received = (double)$tmp_total_received; 
							$total_received_in_satoshi = (int)$this->coin_to_satoshi($total_received);
						}
						else
						{
							$total_received_in_satoshi = -1;
						}
					}
					else
					{
						$total_received_in_satoshi = -1;
					}
				}
				else
				{
					$total_received_in_satoshi = -1;
				}
			}
			else
			{
				$total_received_in_satoshi = -1;
			}
		}
		else
		{
			$total_received_in_satoshi = -1;
		}
		
		return $total_received_in_satoshi;
	}

	/**
	* @method get_balance
	* @desc Query server and return the balance for a given label
	*
	* @param string $label
	* @param int $minimum_confirmations
	* 
	* @return double
	*/
	public function get_balance($label = '', $minimum_confirmations = 1)
	{
		$balance = -1;

		$label					= strip_tags($label);
		$minimum_confirmations	= (int)floor($minimum_confirmations);

		if($minimum_confirmations <= 0)
		{
			$minimum_confirmations = 0;
		}

		if($this->connection)
		{
			$tmp_command_executed = 0;
			try
			{
				$tmp_total_received = $this->connection->getbalance($label, $minimum_confirmations);
				$tmp_command_executed = 1;
			}
			catch(Exception $e)
			{
				$tmp_command_executed = 0;
			}

			if($tmp_command_executed == 1)
			{
				if($tmp_total_received >= 0)
				{
					$balance = (double)$tmp_total_received;
				}
			}
		}

		return $balance;
	}

	/**	
	* @method sendfrom
	* @desc Query server and send coins from an account/address to the specified address
	*
	* @param string $label The label you want to get the coins from
	* @param string $send_to_address The address you want to send to
	* @param int $amount_in_satoshi
	* @param int $minimum_confirmations
	*
	* @return string
	*/
	public function sendfrom($label = '', $send_to_address = '', $amount_in_satoshi = 0, $minimum_confirmations = 1)
	{
		$tx_id = false;
		
		$amount_in_satoshi = (int)$amount_in_satoshi;
		$amount_in_coins = $this->satoshi_to_coin($amount_in_satoshi);
		if($amount_in_coins < 0.0)
		{
			$amount_in_coins = 0.0;
		}
		
		if($amount_in_coins >= 21000000.0)
		{
			$amount_in_coins = 21000000.0;
		}
		
			if($this->connection){
				$tmp_verifymessage_status = '';
				$tmp_command_success = 0;
				
				try
				{
					$tmp_verifymessage_status = $this->connection->sendfrom($label, $send_to_address, $amount_in_coins, $minimum_confirmations, '', '');
					$tmp_command_success = 1;
				}
				catch(Exception $e)
				{
					$tmp_command_success = 0;
				}
				
				if($tmp_command_success == 1)
				{
					$tx_id = $tmp_verifymessage_status;
				}
			}
		
		return $tx_id;
	}

	/**
	* @method sendmany
	* @desc Query server and send coins to more than one addresses
	*
	* @param string $label
	* @param string $send_to_address
	* @param int $minimum_confirmations
	* @param string $comment
	*
	* @return string
	*/
	public function sendmany($label = '', $send_to_address = '', $minimum_confirmations = 1, $comment = '')
	{
		$error_rpc_messag = false;

		if($this->connection)
		{
			$tmp_verifymessage_status = '';
			$tmp_command_success = 0;
			
			try
			{
				$tmp_verifymessage_status = $this->connection->sendmany($label, $send_to_address, $minimum_confirmations, $comment);
				$tmp_command_success = 1;
			}
			catch(Exception $e)
			{
				$tmp_command_success = 0;
			}
			
			
			if($tmp_command_success == 0)
			{
				$error_rpc_message = $tmp_verifymessage_status;
			}
			
		}
			
		return $error_rpc_messag;
	}

	/**
	* @method get_transaction
	* @desc Query server and get information about the requested transaction.
	*
	* @param string $tx_id
	*
	* @return array
	*/
	public function get_transaction($tx_id = '')
	{
		$output["return_status"]		= false;
		
		$output["tx_info"]["amount"]		= (double) 0.00000000;
		$output["tx_info"]["fee"]		= (double) 0.00000000;
		$output["tx_info"]["confirmations"]	= (int) 0;
		$output["tx_info"]["blockhash"]		= (string) '';
		$output["tx_info"]["blockindex"]	= (int) 0;
		$output["tx_info"]["blocktime"]		= (int) 0;
		$output["tx_info"]["txid"]		= (string) '';
		$output["tx_info"]["time"]		= (int) 0;
		$output["tx_info"]["timereceived"]	= (int) 0;
		
		$output["tx_info"]["details"]["account"]	= (string) '';
		$output["tx_info"]["details"]["address"]	= (string) '';
		$output["tx_info"]["details"]["category"]	= (string) '';
		$output["tx_info"]["details"]["amount"]		= (double) 0.00000000;
		$output["tx_info"]["details"]["fee"]		= (double) 0.00000000;

		if($this->connection)
		{
				$tmp_tx_info = '';
				$tmp_command_success = 0;
				
				try
				{
					$tmp_tx_info = $this->connection->gettransaction($tx_id);
					$tmp_command_success = 1;
				}
				catch(Exception $e)
				{
					$tmp_command_success = 0;
				}
				
				if($tmp_command_success == 1)
				{
					$output["return_status"] = true;
					
					$output["tx_info"]["amount"]		= (double) $tmp_tx_info["amount"];
					$output["tx_info"]["fee"]			= (double) $tmp_tx_info["fee"];
					$output["tx_info"]["confirmations"]	= (int) $tmp_tx_info["confirmations"];
					$output["tx_info"]["blockhash"]		= (string) $tmp_tx_info["blockhash"];
					$output["tx_info"]["blockindex"]	= (int) $tmp_tx_info["blockindex"];
					$output["tx_info"]["blocktime"]		= (int) $tmp_tx_info["blocktime"];
					$output["tx_info"]["txid"]			= (string) $tmp_tx_info["txid"];
					$output["tx_info"]["time"]			= (int) $tmp_tx_info["time"];
					$output["tx_info"]["timereceived"]	= (int) $tmp_tx_info["timereceived"];
					

					$output["tx_info"]["details"]["account"]	= (string) $tmp_tx_info["details"][0]["account"];
					$output["tx_info"]["details"]["address"]	= (string) $tmp_tx_info["details"][0]["address"];
					$output["tx_info"]["details"]["category"]	= (string) $tmp_tx_info["details"][0]["category"];
					$output["tx_info"]["details"]["amount"]		= (double) $tmp_tx_info["details"][0]["amount"];
					$output["tx_info"]["details"]["fee"]		= (double) $tmp_tx_info["details"][0]["fee"];
					
				}
		}
		
		return $output;
	}

	/**
	* @method _encode_message
	* @desc Dummy function to promote consistency with code. For example if this library is released and it is found that base64_encode() doesn't do the job right and we need to change it, a dev can just upload an update with out any issues(other than the expected signature verification incompatibilities)
	*
	* @param string $plain_text_string
	* 
	* @return string
	*/
	private function _encode_message($plain_text_string)
	{
		return base64_encode($plain_text_string);
	}

	/**
	* @method _decode_message
	* @desc Dummy function to promote consistency with code. For example if this library is released and it is found that base64_encode() doesn't do the job right and we need to change it, a dev can just upload an update with out any issues(other than the expected signature verification incompatibilities)
	* 
	* @param string $plain_text_string
	* 
	* @return string
	*/
	private function _decode_message($plain_text_string)
	{
		return base64_decode($plain_text_string);
	}

	/**
	* @method _verify_checksum
	* @desc a simple function to call for verifying a checksum with its input contents
	* 
	* @param string $original_string
	* @param string $checksum_string
	* @param string $checksum_algo
	* 
	* @return bool
	*/
	private function _verify_checksum($original_string = '', $checksum_string = '', $checksum_algo = '')
	{	
		$output = false;

		if($checksum_algo == '')
		{
			$checksum_algo = $this->settings["hash_type"];
		}
		
		$original_hash = hash($checksum_algo, $original_string);
		
		if($checksum_string == $original_hash)
		{
			$output = true;
		}
		
		return $output;
	}

	/**
	* @method _generate_random_string
	* @desc Generates a length of random text
	* 
	* @param int $length
	* @param int $character_quick
	* @param string $characters
	* 
	* @return string
	*/
	private function _generate_random_string($length = 4096, $character_quick = 0, $characters = '')
	{				
		$random_string = '';

		if(strlen($characters) < 1)
		{
			$characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		}

		$continue_generating = 1;

		$last_length = 0;
		$last_length_iteration = 0;
			
		while($continue_generating == 1)
		{
			$string = '';
			for ($i = 0; $i < 1024; $i++)
			{
				$string .= $characters[rand(0, strlen($characters) - 1)];
			}
			
			//Hash new random strings until we have more then enough characters
			$random_string .= hash($this->settings["hash_type"], $string);
			
			
			/* Prevent Infinite loops from ever happening by comparing previous values */
			if(strlen($random_string) >= $length)
			{
				$continue_generating = 0;
			}
			
			//Check if the length has changed (Only if we should continue generating though)
			if($continue_generating == 1 && $last_length_iteration >= 2)
			{
				if($last_length >= strlen($random_string))
				{
					//We have detected a possible infinite loop, break the while() statement
					$continue_generating = 0;
				}
			}
			
			
			//Get length and set it as the last length
			$last_length = strlen($random_string);
			
			//Do iterations....
			$last_length_iteration++;
		}
		
		//Strip all characters past the target amount...
		$random_string = substr($random_string, 0, $length);
		
		//Check if this is the target length before returning as success
		if(strlen($random_string) != $length)
		{
			$random_string = false;
		}
		
		return $random_string;
	}

	/**
	* @method satoshi_to_coin
	* @desc Easily convert "satoshi" integer values to coin "decimal" value.
	* 
	* @param int $satoshi_value
	* @param int $round_type
	* 
	* @return decimal
	*/
	public function satoshi_to_coin($satoshi_value, $round_type = 0)
	{
		$display = false;
		
		//Check if inputs are valid and deal with invalid ones
		$process_func = 1;
		if(is_int($satoshi_value) == true)
		{
			$proccess_func = 1;
		}
		else
		{
			$tmp_int_value = (int)floor($satoshi_value);
			if($tmp_int_value == $satoshi_value)
			{
				$proccess_func = 1;
			}
			else
			{
				$proccess_func = 0;
			}	
		}
		
		if($proccess_func == 1)
		{
			$tmp_btc_display = $satoshi_value / 100000000;
			$display = (double) $tmp_btc_display;
		}
		
		return $display;
	}
			
	/**
	* @method coin_to_satoshi
	* @desc Easily convert "decimal" coin values to "satoshi" value.
	* 
	* @param int $coin_amount
	* 
	* @return int
	*/
	public function coin_to_satoshi($coin_amount)
	{
		return (int)($coin_amount * 100000000);
	}
}