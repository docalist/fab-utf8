<?php
/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: DedupValues.php 711 2008-05-23 17:10:04Z daniel.menard.bdsp $
 */

/**
 * M�thodes de d�doublonnage bas�e sur la comparaison des articles 
 * 
 * @package     fab
 * @subpackage  modules
 */

class DedupValues extends DedupTokens
{
    /**
     * Retourne une �quation de recherche contenant les articles pr�sents dans 
     * la valeur pass�e en param�tre.
     *
     * Si un m�me article apparait plusieurs fois, il n'apparaitra qu'une seule 
     * fois dans l'�quation finale.
     * 
     * Si la valeur pass�e en param�tre est vide (null ou chaine vide) un 
     * tableau vide est retourn�.
     * 
     * @param null|string|array $value
     * @return array
     */
    protected function getTokens($value)
    {
        $value=(array) $value;
        foreach ($value as &$item)
            $item='[' . implode(' ', Utils::tokenize($item)) . ']';
        return array_flip($value);
    }
}
