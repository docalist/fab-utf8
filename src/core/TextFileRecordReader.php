<?php
/**
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>, Séverine Ferron <Severine.Ferron@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe de base pour les {@link RecordReader} qui travaillent sur des fichiers
 * texte (AJP, CSV, etc.)
 *
 * Gère le charset du fichier, sait lire une ligne en ignorant les lignes vides, sait
 * générer une erreur en indiquant le numéro de la ligne erronnée.
 *
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>, Séverine Ferron <Severine.Ferron@ehesp.fr>
 */
abstract class TextFileRecordReader extends RecordReader
{
    /**
     * Numéro de la ligne en cours au sein du fichier.
     *
     * @var int
     */
    protected $line = 0;


    /**
     * Charset du fichier d'origine.
     *
     * La NCT s'attend à recevoir des données en ISO-8859-1. Si le charset indiqué est différent,
     * la méthode {@link getLine()} se charge de convertir les caractères dans le bon charset.
     *
     * @var string
     */
    protected $charset = 'ISO-8859-1';


    /**
     * Lit la ligne suivante du fichier.
     *
     * Les lignes sont trimmées (espaces de début et de fin supprimés).
     *
     * Les lignes vides sont ignorées, sauf si $ignoreEmpty est à false.
     *
     * La propriété {@link $line} est incrémentée.
     *
     * @param bool $ignoreEmpty indique s'il faut ignorer les lignes vides
     * (<code>true</code> par défaut).
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
     * Génère une exception en indiquant le nom du fichier en cours et la ligne à laquelle
     * s'est produite l'erreur.
     *
     * La méthode fonctionne comme sprintf : message peut contenir des délimiteurs (%s...) et vous
     * pouvez passer en paramètre les arguments requis.
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
     * Convertit les données passées en paramètre d'un charset vers un autre.
     *
     * L'implémentation par défaut utilise iconv.
     *
     * @param string $string la chaine à convertir
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