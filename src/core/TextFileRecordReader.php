<?php
/**
 * @package     nct
 * @subpackage  common
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>, S�verine Ferron <Severine.Ferron@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe de base pour les {@link RecordReader} qui travaillent sur des fichiers
 * texte (AJP, CSV, etc.)
 *
 * G�re le charset du fichier, sait lire une ligne en ignorant les lignes vides, sait
 * g�n�rer une erreur en indiquant le num�ro de la ligne erronn�e.
 *
 * @package     nct
 * @subpackage  common
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>, S�verine Ferron <Severine.Ferron@ehesp.fr>
 */
abstract class TextFileRecordReader extends RecordReader
{
    /**
     * Num�ro de la ligne en cours au sein du fichier.
     *
     * @var int
     */
    protected $line = 0;


    /**
     * Charset du fichier d'origine.
     *
     * La NCT s'attend � recevoir des donn�es en ISO-8859-1. Si le charset indiqu� est diff�rent,
     * la m�thode {@link getLine()} se charge de convertir les caract�res dans le bon charset.
     *
     * @var string
     */
    protected $charset = 'ISO-8859-1';


    /**
     * Lit la ligne suivante du fichier.
     *
     * Les lignes sont trimm�es (espaces de d�but et de fin supprim�s).
     *
     * Les lignes vides sont ignor�es, sauf si $ignoreEmpty est � false.
     *
     * La propri�t� {@link $line} est incr�ment�e.
     *
     * @param bool $ignoreEmpty indique s'il faut ignorer les lignes vides
     * (<code>true</code> par d�faut).
     *
     * @return string|false
     */
    protected function getLine($ignoreEmpty = true)
    {
        for(;;)
        {
            if (feof($this->file)) return false;
            ++$this->line;
            $line = trim(fgets($this->file));
            if ($line !== '' || ! $ignoreEmpty)
            {
                if ($this->charset !== 'ISO-8859-1')
                    $line = $this->convertCharset($line, $this->charset);

                return $line;
            }
        }
    }


    /**
     * G�n�re une exception en indiquant le nom du fichier en cours et la ligne � laquelle
     * s'est produite l'erreur.
     *
     * La m�thode fonctionne comme sprintf : message peut contenir des d�limiteurs (%s...) et vous
     * pouvez passer en param�tre les arguments requis.
     *
     * @param string $message
     * @param mixed $args...
     */
    protected function fileError($message, $args = null)
    {
        $args = func_get_args();
        array_shift($args);
        $message = get_class($this) . ',' . $this->path . ':' . $this->line . ', ' . $message;
        throw new Exception(vsprintf($message, $args));
    }


    /**
     * Convertit les donn�es pass�es en param�tre d'un charset vers un autre.
     *
     * L'impl�mentation par d�faut utilise iconv.
     *
     * @param string $string la chaine � convertir
     * @param string $from le charset d'origine
     * @param string $to le charset de destination
     *
     * @return string la chaine convertie
     */
    protected function convertCharset($string, $from, $to = 'ISO-8859-1')
    {
        return iconv($from, $to, $string);
    }
}