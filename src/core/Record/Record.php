<?php
/**
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>, Séverine Ferron <Severine.Ferron@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe de base pour toutes les classes <code>Record</code> de la chaine de traitement.
 *
 * La classe <code>Record</code> enrichit la classe {@link AbstractRecord} en
 * ajoutant des méthodes transversales utilisables par toutes les interfaces.
 *
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>, Séverine Ferron <Severine.Ferron@ehesp.fr>
 */
abstract class Record extends AbstractRecord
{
    /**
     * Garantit que la mention "et al.", si elle figure dans les auteurs, se trouve en dernière
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