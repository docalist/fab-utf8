<?php
/**
 * This file is part of the Fab package.
 *
 * For copyright and license information, please view the
 * LICENSE.txt file that was distributed with this source code.
 *
 * @package     Fab
 * @subpackage  Indexing
 * @author      Daniel Ménard <Daniel.Menard@laposte.net>
 * @version     SVN: $Id$
 */
namespace Fab\Indexing;

/**
 * Indexe un champ booléen.
 *
 * Génère le mot-clé 'true' ou le mot-clé 'false' selon la valeur du champ.
 *
 * Il n'y a pas besoin d'appliquer au préalable un tokenizer ({@link Lowercase}
 * ou {@link StripTags}) pour pouvoir utiliser cet analyseur.
 */
class Boolean implements AnalyzerInterface
{
    /**
     * Termes à générer si le booléen est à true.
     *
     * @var mixed
     */
    protected static $true = 'true';

    /**
     * Termes à générer si le booléen est à false.
     *
     * @var mixed
     */
    protected static $false = 'false';

    public function analyze(AnalyzerData $data)
    {
        foreach ($data->content as $value)
        {
            $data->keywords[] = $value ? static::$true : static::$false;
        }
    }
}