<?php
/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: DedupFirstValue.php 693 2008-05-07 15:26:05Z daniel.menard.bdsp $
 */

/**
 * M�thodes de d�doublonnage bas�e sur la comparaison des articles 
 * 
 * @package     fab
 * @subpackage  modules
 */

class DedupFirstValue extends DedupValues
{
    public function getEquation($value)
    {
        $t=$this->getTokens($value);
        if (count($t)===0) return '';
        return key($t);
    }
    
    public function compare($a, $b)
    {
        $first=$this->getEquation($a);
        $tb=$this->getTokens($b);

        return isset($tb[$first]) ? 100 : 0;
    }
}
