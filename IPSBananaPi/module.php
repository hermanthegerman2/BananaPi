<?php

class IPSBananaPi extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger("Intervall", 3600);
        $this->RegisterTimer("ReadBananaPiInfo", 0, 'IPSBananaPi_Update($_IPS[\'TARGET\']);');
    }
    
    public function Destroy()
    {
        $this->UnregisterTimer("ReadBananaPiInfo");
        
        //Never delete this line!
        parent::Destroy();
    }


    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        
        //Variablenprofil erstellen
        $this->RegisterProfileInteger("capacity", "", "", " mAh", "0", "1000", "1");
        
        $this->RegisterVariableInteger("cpu0freq", "CPU0 Frequenz", "~Hertz");
        $this->RegisterVariableInteger("cpu1freq", "CPU1 Frequenz", "~Hertz");
        $this->RegisterVariableFloat("voltage", "Spannung", "~Volt");
        $this->RegisterVariableFloat("current", "Strom", "~Ampere");
        $this->RegisterVariableFloat("chargevoltage", "Ladespannung",  "~Volt");
        $this->RegisterVariableFloat("chargecurrent", "Ladestrom", "~Ampere");
        $this->RegisterVariableString("status", "Status");
        $this->RegisterVariableInteger("charge", "Ladezustand", "~Intensity.1");
        $this->RegisterVariableString("control", "Modus");
        $this->RegisterVariableInteger("capacity", "Akkukapazität", "capacity");
        $this->Update();
        $this->SetTimerInterval("ReadBananaPiInfo", $this->ReadPropertyInteger("Intervall")); 
    }
    
    public function Update()
    {
        $this->SetValueInteger("cpu0freq", (exec("cat /sys/bus/cpu/devices/cpu0/cpufreq/cpuinfo_cur_freq")));
        //
        $this->SetValueInteger("cpu1freq", (exec("cat /sys/bus/cpu/devices/cpu1/cpufreq/cpuinfo_cur_freq")));
        //
        $this->SetValueFloat("voltage", (exec("cat /sys/class/power_supply/ac/voltage_now"))/1000000);
        //
        $this->SetValueFloat("current", (exec("cat /sys/class/power_supply/ac/current_now"))/1000000);
        //
        $this->SetValueFloat("chargevoltage", (exec("cat /sys/class/power_supply/battery/voltage_now"))/1000000);
        //
        $this->SetValueFloat("chargecurrent", (exec("cat /sys/class/power_supply/battery/current_now"))/1000000);
        //
        $this->SetValueString("status", (exec("cat /sys/class/power_supply/battery/status")));
        //
        $this->SetValueInteger("charge", (int)(exec("cat /sys/class/power_supply/battery/capacity")));
         //
        $this->SetValueString("control", (exec("cat /sys/class/power_supply/battery/power/control")));
        //
        $this->SetValueInteger("capacity", (exec("cat /sys/class/power_supply/battery/energy_full_design")) /1);
        

    }
    
################## PRIVATE
    
    private function SetValueInteger($Ident, $value)
    {
        $id = $this->GetIDForIdent($Ident);
        if (GetValueInteger($id) <> $value)
        {
            SetValueInteger($id, $value);
            return true;
        }
        return false;
    }
    
    private function SetValueFloat($Ident, $value)
    {
        $id = $this->GetIDForIdent($Ident);
        if (GetValueFloat($id) <> $value)
        {
            SetValueFloat($id, $value);
            return true;
        }
        return false;
    }
    
    private function SetValueString($Ident, $value)
    {
        $id = $this->GetIDForIdent($Ident);
        if (GetValueString($id) <> $value)
        {
            SetValueString($id, $value);
            return true;
        }
        return false;
    }
    
    protected function RegisterTimer($Name, $Interval, $Script)
    {
        $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
        if ($id === false)
            $id = 0;
        if ($id > 0)
        {
            if (!IPS_EventExists($id))
                throw new Exception("Ident with name " . $Name . " is used for wrong object type", E_USER_WARNING);
            if (IPS_GetEvent($id)['EventType'] <> 1)
            {
                IPS_DeleteEvent($id);
                $id = 0;
            }
        }
        if ($id == 0)
        {
            $id = IPS_CreateEvent(1);
            IPS_SetParent($id, $this->InstanceID);
            IPS_SetIdent($id, $Name);
        }
        IPS_SetName($id, $Name);
        IPS_SetHidden($id, true);
        IPS_SetEventScript($id, $Script);
        if ($Interval > 0)
        {
            IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, $Interval);
            IPS_SetEventActive($id, true);
        } else
        {
            IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, 1);
            IPS_SetEventActive($id, false);
        }
    }
    protected function UnregisterTimer($Name)
    {
        $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
        if ($id > 0)
        {
            if (!IPS_EventExists($id))
                throw new Exception('Timer not present', E_USER_NOTICE);
            IPS_DeleteEvent($id);
        }
    }
    protected function SetTimerInterval($Name, $Interval)
    {
        $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
        if ($id === false)
            throw new Exception('Timer not present', E_USER_WARNING);
        if (!IPS_EventExists($id))
            throw new Exception('Timer not present', E_USER_WARNING);
        $Event = IPS_GetEvent($id);
        if ($Interval < 1)
        {
            if ($Event['EventActive'])
                IPS_SetEventActive($id, false);
        }
        else
        {
            if ($Event['CyclicTimeValue'] <> $Interval)
                IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, $Interval);
            if (!$Event['EventActive'])
                IPS_SetEventActive($id, true);
        }
    }
    
    protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {
    		if (!IPS_VariableProfileExists($Name)) {
    				IPS_CreateVariableProfile($Name, 1);
    		}
    		else {
    				$profile = IPS_GetVariableProfile($Name);
    				if ($profile['ProfileType'] != 1) {
    						throw new Exception("Variable profile type does not match for profile ".$Name);
    				}
    		}	 
    		
    		IPS_SetVariableProfileIcon($Name, $Icon);
    		IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
    		IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
    }
    
}
?>
