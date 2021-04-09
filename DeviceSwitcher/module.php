<?php

// Klassendefinition
class DeviceSwitcher extends IPSModule {
 
	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {
		// Diese Zeile nicht löschen
		parent::__construct($InstanceID);

		// Selbsterstellter Code
	}

	// Überschreibt die interne IPS_Create($id) Funktion
	public function Create() {
		
		// Diese Zeile nicht löschen.
		parent::Create();

		// Properties
		$this->RegisterPropertyString("Sender","DeviceSwitcher");
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyBoolean("DebugOutput",false);
		$this->RegisterPropertyInteger("SourceVariable",0);
		$this->RegisterPropertyBoolean("InvertSource",false);
		$this->RegisterPropertyInteger("TargetStatusVariable",0);
		
		// Variables
		$this->RegisterVariableBoolean("Status","Status","~Switch");
		
		//Actions
		$this->EnableAction("Status");
		
		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'DEVSWITCHER_RefreshInformation($_IPS[\'TARGET\']);');
    }

	public function Destroy() {

		// Never delete this line
		parent::Destroy();
	}
 
	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {

		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);
		
		$this->RegisterMessage($this->ReadPropertyInteger("SourceVariable"), VM_UPDATE);
			
		// Diese Zeile nicht löschen
		parent::ApplyChanges();
	}


	public function GetConfigurationForm() {
        	
		// Initialize the form
		$form = Array(
            		"elements" => Array(),
					"actions" => Array()
        		);

		// Add the Elements
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "RefreshInterval", "caption" => "Refresh Interval");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "DebugOutput", "caption" => "Enable Debug Output");
		$form['elements'][] = Array("type" => "SelectVariable", "name" => "SourceVariable", "caption" => "Source Variable (must be boolean)");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "InvertSource", "caption" => "Invert source variable (false turns the device on)");
		$form['elements'][] = Array("type" => "SelectVariable", "name" => "TargetStatusVariable", "caption" => "Target Status Variable");
		
		// Add the buttons for the test center
		$form['actions'][] = Array(	"type" => "Button", "label" => "Refresh", "onClick" => 'DEVSWITCHER_RefreshInformation($id);');

		// Return the completed form
		return json_encode($form);

	}
	
	protected function LogMessage($message, $severity = 'INFO') {
		
		$logMappings = Array();
		$logMappings['DEBUG'] 	= 'KL_DEBUG';
		$logMappings['INFO']	= 'KL_MESSAGE';
		$logMappings['NOTIFY']	= 'KL_NOTIFY';
		$logMappings['WARN'] 	= 'KL_WARNING';
		$logMappings['CRIT']	= 'KL_ERROR';
		
		if ( ($severity == 'DEBUG') && ($this->ReadPropertyBoolean('DebugOutput') == false )) {
			
			return;
		}
		
		$messageComplete = $severity . " - " . $message;
		parent::LogMessage($this->ReadPropertyString('Sender') . " - " . $this->InstanceID, $messageComplete);
	}

	public function RefreshInformation() {

		$this->LogMessage("Refresh in Progress", "DEBUG");
		
		if (! GetValue($this->GetIDForIdent("Status")) ) {
			
			$this->LogMessage("Device will not be checked because checking is deactivated","DEBUG");
			return;
		}
		
		$sourceValue = GetValue($this->ReadPropertyInteger("SourceVariable"));
		
		if ($this->ReadPropertyBoolean("InvertSource")) {
			
			$sourceValue = ! $sourceValue;
		}
		
		
		if (GetValue($this->ReadPropertyInteger("TargetStatusVariable")) != $sourceValue) {
			
			$this->LogMessage("Switching Target Device","DEBUG");
			$this->RequestActionWithBackOff($this->ReadPropertyInteger("TargetStatusVariable"), $sourceValue);
		}
		
	}
	
	protected function RequestActionWithBackOff($variable, $value) {
		
		$retries = 4;
		$baseWait = 100;
		
		for ($i = 0; $i <= $retries; $i++) {
			
			$wait = $baseWait * $i;
			
			if ($wait > 0) {
				
				$this->LogMessage("Waiting for $wait milliseconds, retry $i of $retries", "DEBUG");
				IPS_Sleep($wait);
			}
			
			$result = RequestAction($variable, $value);
			
			// Return success if executed successfully
			if ($result) {
				
				return true;
			}
			else {
				
				$this->LogMessage("Switching Variable $variable to Value $value failed, but will be retried", "WARN");
			}
			
		}
		
		// return false as switching was not possible after all these times
		$this->LogMessage("Switching Variable $variable to Value $value failed after $retries retries. Aborting", "CRIT");
		return false;
	}

	public function RequestAction($Ident, $Value) {
	
	
		switch ($Ident) {
		
			case "Status":
				SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			default:
				throw new Exception("Invalid Ident");
		}
	}
	
	public function MessageSink($TimeStamp, $SenderId, $Message, $Data) {
	
		$this->LogMessage("$TimeStamp - $SenderId - $Message - " . implode(" ; ",$Data), "DEBUG");
		
		$this->RefreshInformation();
	}
	
}
