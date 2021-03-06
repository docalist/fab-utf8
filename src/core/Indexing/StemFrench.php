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
 * Stemmer français utilisant xapian.
 *
 * Remplace les termes par leur version lemmatisée.
 */
class StemFrench implements AnalyzerInterface
{
    protected $stemmer;

    public function __construct()
    {
        $this->stemmer = new \XapianStem('french');
    }

    public function analyze(AnalyzerData $data)
    {
        $data->map(array('terms', 'postings'), array($this->stemmer, 'apply'));
    }
}