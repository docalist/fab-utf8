<?php
/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: DedupYears.php 693 2008-05-07 15:26:05Z daniel.menard.bdsp $
 */

/**
 * M�thodes de d�doublonnage bas�e sur la comparaison des ann�es.
 * 
 * Seules les ann�es �crites sous la forme d'un nombre de quatre chiffres 
 * commen�ant par 1 ou 2 sont prises en comptes (i.e. 1998 ou 2007 mais pas 98) 
 * 
 * @package     fab
 * @subpackage  modules
 */

class DedupYears extends DedupTokens
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
        // Si c'est un tableau, on le lin�arise
        if (is_array($value)) $value=implode('�', $value);
        
        if (preg_match_all('~\b(?:1|2)\d{3}\b~', $value, $matches))
            return array_flip($matches[0]);
        else
            return array();
    }
}
