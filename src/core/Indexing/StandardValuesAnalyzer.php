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
 * Analyseur standard pour les champs articles (typdoc, auteurs, motsclés...)
 *
 * Exécute dans l'ordre les analyseurs suivants :
 * - {@link \Fab\Indexing\Lookup}
 * - {@link \Fab\Indexing\Lowercase}
 * - {@link \Fab\Indexing\Phrases}
 * - {@link \Fab\Indexing\Keywords}
 * - {@link \Fab\Indexing\Countable}
 */
class StandardValuesAnalyzer extends MetaAnalyzer
{
    public function __construct()
    {
        parent::__construct(array
        (
        	'Fab\Indexing\Lookup',
        	'Fab\Indexing\Lowercase',
        	'Fab\Indexing\Phrases',
        	'Fab\Indexing\Spellings',
        	'Fab\Indexing\Keywords',
        	'Fab\Indexing\Countable'
        ));
    }
}