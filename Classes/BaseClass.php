<?php

class BaseClass {
    private array $original = [];
    private array $attributes = [];
    private Database $db;
    private string $tableName;
    
    protected array $attributesDate = [];
    protected array $attributesPhone = [];
    protected array $attributestMoney = [];
    
    protected string $defaultFormatDate  = 'd/m/Y';
    protected string $defaultFormatPhone = 'XXX XXX XXXX';
    protected string $defaultMoneySymbol = '€';
    
    
    public function __construct(Database $db, string $tableName) {
        $this->db = $db;
        $this->tableName = $tableName;
    }
    
    public function fetch($id) {
        $this->db->query("SELECT * FROM {$this->tableName} WHERE id = :id", true, [':id' => $id]);
        $fetch = $this->db->single();
        if(!empty($fetch)) {
            $this->original = $fetch;
            $this->attributes = $fetch;
            
            return true;
        } else {
            return false;
        }
        
    }
    
    public function fetchColumns($columnsName, $id = null) {
        if ($id === null) {
            $id = $this->attributes['id'] ?? null;
            
            if ($id === null) {
                throw new Exception("Nessun ID fornito o trovato nell'istanza.");
            }
        }
        
        if(is_array($columnsName)) {
            $columns = implode(', ', trim($columnsName));
        } else {
            $columns = trim($columnsName);
        }
        
        if(empty($columns)) {
            throw new Exception("Colonne non specificate.");
        }
        
        $this->db->query("SELECT {$columnsName} FROM {$this->tableName} WHERE id = :id", true, [':id' => $id]);
        $fetch = $this->db->single();
        if(!empty($fetch)) {
            foreach ($fetch as $key => $value) {
                $this->attributes[$key] = $value;
                $this->original[$key] = $value;
            }
            
            return true;
        } else {
            return false;
        }
    }
    
    public function delete() {
        if(empty($this->original['id'])) {
            throw new Exception("Nessun ID fornito.");
        }
        $this->db->query("DELETE FROM {$this->tableName} WHERE id = :id", true, [':id' => $this->original['id']]);
    }
    
    public function save() {
        $dirty = $this->getDirty();
        
        if (empty($dirty)) {
            return true;
        }
        
        if (isset($this->original['id'])) {
            $sets = [];
            $params = [];
            
            foreach ($dirty as $key => $value) {
                $sets[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
            
            $setsString = implode(', ', $sets);
            $sql = "UPDATE `{$this->tableName}` SET {$setsString} WHERE id = :id";
            $params[':id'] = $this->original['id'];
            
            $this->db->query($sql, true, $params);
        } else {
            $columns = array_keys($dirty);
            $placeholders = array_map(function($column) {
                return ":{$column}";
            }, $columns);
            
            $columnsString = implode(', ', $columns);
            $placeholdersString = implode(', ', $placeholders);
            
            $sql = "INSERT INTO {$this->tableName} ({$columnsString}) VALUES ({$placeholdersString})";
            
            $params = [];
            foreach ($dirty as $key => $value) {
                $params[":{$key}"] = $value;
            }
            
            $this->db->query($sql, true, $params);
            $this->original['id'] = $this->db->lastInsertId();
            $this->attributes['id'] = $this->original['id'];
        }
        
        return true;
    }
    
    public function fill($data) {
        foreach ($data as $key => $value) {
            $this->__set($key, $value);
        }
    }
    
    public function __set($key, $value) {
        if($key == 'id') {
            throw new Exception("Non puoi modificare l'id.");
        }
        
        if(in_array($key, $this->attributesDate)) {
            $value = $this->formatDate($value, 'Y-m-d');
        } elseif(in_array($key, $this->attributesPhone)) {
            $value = $this->formatPhone($value);
        } elseif(in_array($key, $this->attributestMoney)) {
            $value = $this->formatMoney($value);
        }
        $this->attributes[$key] = $value;
    }
    
    public function __get($key) {
        if(in_array($key, $this->attributesDate)) {
            $value = $this->formatDate($this->attributes[$key]);
        } elseif(in_array($key, $this->attributesPhone)) {
            $value = $this->formatPhone($this->attributes[$key]);
        } elseif(in_array($key, $this->attributestMoney)) {
            $value = $this->formatMoney($this->attributes[$key]);
        } else {
            $value = $this->attributes[$key];
        }
        return $value ?? null;
    }
    
    public function __toString() {
        $data = [];
        if(!empty($this->attributes)) {
            foreach ($this->attributes as $key => $value) {
                $data[$key] = $this->__get($key);
            }
        }
        return json_encode($data);
    }
    
    //region Get Functions
    public function toArray(): array {
        $data = [];
        if (!empty($this->attributes)) {
            foreach ($this->attributes as $key => $value) {
                $data[$key] = $this->__get($key);
            }
        }
        return $data;
    }
    public function getDirty() {
        $dirty = [];
        if(empty($this->original)) {
            return $this->attributes;
        }
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }
    
    public function getClearData($key) {
        if(empty($key)) {
            return $this->attributes ?? null;
        }
        return $this->attributes[$key] ?? null;
    }
    //endregion
    
    //region Checking Functions
    public function isDirty() {
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                return true;
            }
        }
        foreach ($this->original as $key => $value) {
            if (!array_key_exists($key, $this->attributes)) {
                return true;
            }
        }
        return false;
    }
    
    private function isTimestamp($value) {
        if (!is_numeric($value)) {
            return false;
        }
        
        $intValue = (int) $value;
        
        if ((string) $intValue !== (string) $value) {
            return false;
        }
        
        $minTimestamp = 0;
        $maxTimestamp = strtotime('+20 years');
        
        return ($intValue >= $minTimestamp && $intValue <= $maxTimestamp);
    }
    
    private function isValidPhone($phone) {
        $clearPhone = $this->clearPhone($phone);
        
        if(empty($clearPhone)) {
            return false;
        }
        $regex = '/^\+?(\d{7,15})$/';
        
        return preg_match($regex, $clearPhone);
    }
    // endregion
    
    // region Formatting Functions
    private function formatDate($dateInput, $outputFormat = null) {
        $outputFormat = $outputFormat ?? $this->defaultFormatDate;
        if ($this->isTimestamp($dateInput)) {
            $timestamp = (int)$dateInput;
            try {
                $dateTime = (new DateTime())->setTimestamp($timestamp);
                return $dateTime->format($outputFormat);
            } catch (Exception $e) {
                return date($outputFormat, $timestamp);
            }
        }
        
        try {
            $dateTime = new DateTime($dateInput);
            return $dateTime->format($outputFormat);
        } catch (Exception $e) {
            $timestamp = strtotime($dateInput);
            if ($timestamp !== false) {
                return date($outputFormat, $timestamp);
            }
        }
        
        return false;
    }
    
    private function formatPhone($phone, $outputFormat = null) {
        if(!$this->isValidPhone($phone)) {
            return false;
        }
        $outputFormat = $outputFormat ?? $this->defaultFormatPhone;
        $clearPhone = $this->clearPhone($phone);
        
        $format = str_split($outputFormat);
        $i = 0;
        $result = '';
        
        foreach ($format as $char) {
            if (strtolower($char) === 'x') {
                if ($i < 10) {
                    $result .= $clearPhone[$i];
                    $i++;
                }
            } else {
                $result .= $char;
            }
        }
        
        return $result;
    }
    
    private function formatMoney($money, $symbol = null) {
        $symbol = $symbol ?? $this->defaultMoneySymbol;
        $money = (float) str_replace(',', '.', trim($money));
        
        if (!is_numeric($money)) {
            return false;
        }
        
        $formattedNumber = number_format($money, 2, ',', '.');
        return "{$formattedNumber} {$symbol}";
    }
    // endregion
    
    //region Utilities Functions
    private function clearPhone($phone) {
        return preg_replace('/[^\d\s\-\(\)]/', '', $phone);
    }
    //endregion
}