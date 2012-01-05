<?php
/**
 * @package     nct
 * @subpackage  common
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>, S�verine Ferron <Severine.Ferron@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe de base pour toutes les classes <code>Record</code> de la chaine de traitement.
 *
 * La classe <code>Record</code> enrichit la classe {@link AbstractRecord} en
 * ajoutant des m�thodes transversales utilisables par toutes les interfaces.
 *
 * @package     nct
 * @subpackage  common
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>, S�verine Ferron <Severine.Ferron@ehesp.fr>
 */
abstract class Record extends AbstractRecord
{
    /**
     * Garantit que la mention "et al.", si elle figure dans les auteurs, se trouve en derni�re
     * position.
     *
     * @param $field
     * @return $this
     */
    protected function etalAtEnd($field)
    {
        if ($this->has($field, 'et al', self::CMP_TOKENIZE))
            $this->clear($field, 'et al', self::CMP_TOKENIZE)->add('et al.');

        return $this;
    }
}