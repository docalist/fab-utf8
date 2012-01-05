<?php
/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: NotFound.php 921 2008-11-27 16:24:04Z daniel.menard.bdsp $
 */



/**
 * Module NotFound
 * 
 * G�n�re une erreur "404 - page non trouv�e".
 * Ce module est appell� automatiquement lorsqu'on n'est pas en mode debug
 * et que l'utilisateur demande un module ou une action qui n'existent 
 * pas.
 * 
 * En mode debug, ce module n'est pas appell�, fab affiche � la place une
 * exception permettant de v�rifier l'erreur.
 * 
 * @package     fab
 * @subpackage  modules
 */
class NotFound extends Module
{
    public function preExecute()
    {
        if (! headers_sent())
            header("HTTP/1.0 404 Not Found");
    }

	public function actionIndex()
    {
        Template::run(config::get('template'));
    }
}
?>