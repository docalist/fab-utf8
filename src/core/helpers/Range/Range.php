<?php


/*
 * Range objet d'it�ration
 * 
 * Permet notamment de boucler entre deux valeurs
 * impl�mente l'interface Iterator
 *
 * @param $start $end pass�s au constructeur sont les deux valeurs entre lesquelles l'it�ration est possible
 * @param $step optionnel indique le pas d'incr�mentation de min vers max lors de l'int�ration
 *          si non renseign�, d�termin� en fonction de $start et de $end
 */
 class Range implements Iterator
 {
    
    private $start = 0;       // valeur de d�part de l'it�ration
    private $end = 0;         // valeur de fin de l'it�ration
    private $current = 0;     // pointeur valeur courante
    private $step = 0;        // le pas de l'it�ration
    private $key = 0;         // cl� de l'�l�ment en cours

    public function __construct($start, $end, $step = 0)
    {
        // Teste les cas pour lequel une it�ration n'a pas de sens
        if ( gettype($start) === 'string' || gettype($end) === 'string' )
        {
            if ( gettype($start) !== gettype($end) )
                throw new Exception('Impossible de boucler entre une valeur de type ' . gettype($start) . ' et une valeur de type '. gettype($end) . '.');
                
            if ( gettype($step) === 'double' )
                throw new Exception('Impossible de boucler sur des caract�res avec un pas de type r�el.');
                
            if ( strlen($start) > 1 || strlen($end) > 1)
                throw new Exception('Impossible de boucler sur des cha�nes de caract�res.');
            
            // si $start minu et $end maju ou $start $end minu et $start maju, exception
            if ( (strtolower($start) == $start && $end >= 'A' && $end <= 'Z')
                || (strtolower($end) == $end && $start >= 'A' && $end <= 'Z') )
                throw new Exception('Impossible de boucler entre une minuscule et une majuscule et vice-versa.');
        }
        
        if ($step == 0)     // alors, valeur de step � d�terminer en fonction de $start et $end
        {
            if ($start <= $end )
                $this->step = 1;
            else
                $this->step = -1;
        }
        else        // la valeur de $step est celle pass�e en param�tre
        {            
            // Autrement, initialiser $step
            $this->step = $step;   
        }
        
        if ( ($step >= 0 && $start <= $end ) || ($step <= 0 && $start >= $end) )
        {
            $this->start = $start;
            $this->end = $end;
        }
        else        // entra�nera boucle infinie
        {
            throw new Exception('Boucle infinie');
        }
    }
    
    public function __destruct()
    {
        $this->start = 0;
        $this->end = 0;
        $this->step = 0;
    }
    
    // Interface Iterator
    
    public function rewind()
    {
        $this->current = $this->start;
        $this->key = 0;
    }
 
    public function current()
    {
        return $this->current;
    }
     
    public function key()
    {
        return $this->key;
    }
     
    public function next()
    {
        if(gettype($this->start) === 'string')
        {
            $this->current = chr(ord($this->current) + $this->step);    
        }
        else
        {
            $this->current += $this->step;
        }

        // Si on travaille avec des r�els, prudence : 1.0+1 != 2.0
        // La doc php indique que :
        // - la pr�cision d�pend de la plate-forme utilis�e
        // - 14 chiffres apr�s la virgule est une pr�cision assez r�pandue
        // donc on tronque � 10 chiffres pour ne pas avoir de probl�mes
        if (is_float($this->current))
            $this->current=round($this->current, 10);
        
        ++$this->key;
    }
     
    public function valid()
    {

        if(gettype($this->start) === 'string')
        {
            return $this->step > 0 ? ord($this->current) <= ord($this->end) : ord($this->current) >= ord($this->end);
        }
        else
        {
            return $this->step > 0 ? $this->current <= $this->end : $this->current >= $this->end;
        }
    }
 }
 
?>