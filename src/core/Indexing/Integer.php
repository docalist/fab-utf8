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
 * Indexe un entier.
 *
 * Cet analyseur ajoute simplement un terme pour chacun des entiers présents dans le champ.
 *
 * Cet analyseur peut être utilisé tout seul : il n'y a pas besoin d'appliquer au
 * préalable un tokenizer tel que {@link Lowercase}.
 */
class Integer implements AnalyzerInterface
{
    public function analyze(AnalyzerData $data)
    {
        foreach ($data->content as $value)
        {
            $data->terms[] = number_format ((int) $value, 0 , 'nu', '');
        }
    }
}