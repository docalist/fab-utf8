<?php
/**
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>, Séverine Ferron <Severine.Ferron@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe de base pour les {@link RecordReader} qui travaillent sur des fichiers au format AJP
 * (format dit "ajout piloté").
 *
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>, Séverine Ferron <Severine.Ferron@ehesp.fr>
 * @version     SVN: $Id$
 */
abstract class AjpReader extends TextFileRecordReader
{
    /**
     * Lit le prochain enregistrement du fichier.
     *
     * @return false|Record un tableau contenant l'enregistrement lu ou <code>false</code>
     * si la fin de fichier est atteinte.
     */
    public function read()
    {
        $this->checkFile();

        // Initialise l'enregistrement
        if (! $this->record) return false;
        $this->record->clear();

        // Lit le premier nom de champ, ignore les lignes vides et les enregistrements vides
        for(;;)
        {
            if (false === $field = $this->getLine()) return $this->record = false;
            if ($field !== '//') break;
        }

        // Vérifie que l'enregistrement commence par un nom de champ
        if (! $this->record->isField($field))
            $this->fileError("l'enregistrement AJP ne commence pas par un nom de champ valide.");

        // Charge toute la notice
        $content = '';
        for(;;)
        {
            // Lit la ligne suivante
            $value = $this->getLine();

            // Teste la fin d'enregistrement
            if ($value === false || $value === '//') break;

            // Nouveau champ
            if ($this->record->isField($value))
            {
                if ($content)
                    $this->record->store($field, $content);

                $content = '';
                $field = $value;
            }

            // Début ou suite du champ
            else
            {
                if (substr($content, -1) === '-')
                    $content = substr($content, 0, -1) . $value;
                else
                    $content = ltrim("$content $value");
            }
        }

        // Stocke le dernier champ
        if ($content)
            $this->record->store($field, $content);

        // Met à jour le numéro d'enreg
        ++$this->recordNumber;

        // Terminé.
        return $this->record;
    }
}