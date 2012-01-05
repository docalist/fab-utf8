<?php
/**
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>, Séverine Ferron <Severine.Ferron@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe de base pour les interfaces d'import qui travaillent sur des fichiers au format AJP
 * (format dit "ajout piloté").
 *
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>, Séverine Ferron <Severine.Ferron@ehesp.fr>
 * @version     SVN: $Id$
 */
class AjpImport extends TextImport
{
    /**
     * Lit le prochain enregistrement du fichier.
     *
     * @return false|array un tableau contenant l'enregistrement lu ou false si la fin de fichier
     * est atteinte.
     */
    public function getRecord()
    {
        // Initialise l'enregistrement
        $this->record = array();

        // Lit le premier nom de champ, ignore les lignes vides et les enregistrements vides
        for(;;)
        {
            if (false === $field = $this->getLine()) return false;
            if ($field !== '//') break;
        }

        // Vérifie que l'enregistrement commence par un nom de champ
        if (! array_key_exists($field, $this->format))
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
            if (array_key_exists($value, $this->format))
            {
                $this->store($field, $content);
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
        $this->store($field, $content);

        // Tansfère les champs du format source dans les champs du format destination
        foreach($this->format as $from=>$to)
        {
            if ($from === $to || ! isset($this->record[$from])) continue;
            if (is_null($to))
                unset($this->record[$from]);
            else
                $this->transfert("$to,$from", $to);
        }

        // Terminé.
       return $this->record;
    }


    /**
     * Stocke le contenu d'un champ
     *
     * @param string $field le nom du champ.
     * @param scalar|array $content le contenu à ajouter au champ.
     */
    private function store($field, & $content)
    {
        if ($content && $field)
        {
            if (isset($this->multiple[$field]))
            {
                $content = array_map('trim', explode($this->sep, $content));
                if (count($content) === 1) $content = $content[0];
            }
            if (! isset($this->record[$field]))
            {
                $this->record[$field] = $content;
            }
            elseif (is_scalar($this->record[$field]))
            {
                if (is_array($content))
                    array_unshift($content, $this->record[$field]);
                else
                    $content = array($this->record[$field], $content);

                $this->record[$field] = $content;
            }
            else
            {
                $this->record[$field] = array_merge($this->record[$field], (array) $content);
            }
        }
        $content = '';
    }
}