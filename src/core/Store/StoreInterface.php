<?php
/**
 * This file is part of the Fab package.
 *
 * For copyright and license information, please view the
 * LICENSE.txt file that was distributed with this source code.
 *
 * @package     Fab
 * @subpackage  Store
 * @author      Daniel Ménard <Daniel.Menard@laposte.net>
 * @version     SVN: $Id$
 */
namespace Fab\Store;

use Fab\Schema\Schema;
use Fab\Store\SearchRequest;

/**
 * Interface des bases de données.
 *
 */
interface StoreInterface
{
    /**
     * Crée un nouvel objet Store.
     *
     * Les options disponibles dépendent du backend utilisé.
     * Elles déterminent s'il faut créer une base de données
     * ou ouvrir une base existante, si la base doit être
     * ouverte en lecture seule ou en lecture/écriture, etc.
     *
     * @param array $options
     */
    public function __construct(array $options = array());

    /**
     * Retourne un document unique identifié par son ID.
     *
     * @param int $id l'ID du document recherché.
     *
     * @return DocumentInterface le document recherché ou null si
     * l'ID indiqué n'existe pas dans la collection.
     */
    public function get($id);

    /**
     * Ajoute ou modifie un document.
     *
     * Si le document ne figure pas déjà dans la collection (i.e. il
     * n'a pas encore d'ID), il est ajouté, sinon, il est mis à jour.
     *
     * Vous pouvez passer en paramètre un tableau ou un objet itérable.
     *
     * @param array|Traversable $document
     */
    public function put($document);

    /**
     * Supprime un document de la collection.
     *
     * @param int $id l'ID du document à supprimer.
     *
     * @return int le nombre d'enregistrements supprimés.
     */
    public function delete($id);

    /**
     * Recherche des documents.
     *
     * @param SearchRequest $request
     *
     * @return DocumentSetInterface
     */
    public function find(SearchRequest $request);

    // Fonctions d'information

    /**
     * Indique si la base de données est en lecture seule.
     */
    public function isReadonly();


    // Manipulation du schéma de la base

    /**
     * Retourne le schéma de la base.
     *
     * @return \Fab\Schema\Schema
     */
    public function getSchema();

    /**
     * Modifie le schéma de la base.
     *
     * @param \Fab\Schema\Schema $schema
     */
    public function setSchema(Schema $schema);
}