<?php
/**
 * @package     Bdsp
 * @subpackage  core
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id: Singleton.php 925 2008-12-02 15:18:52Z daniel.menard.bdsp $
 */

/**
 * Interface d�finissant un singleton.
 * 
 * Cette interface sert essentiellement de "signature" aux modules qui 
 * fonctionnent comme des singletons. Pour tester si un module est un 
 * singleton, il suffit alors d'utiliser :
 * <code>if ($module instanceOf 'Singleton') ...</code>.
 * 
 * Pour qu'une classe fonctionne r�ellement comme un Singleton, celle-ci doit
 * normallement impl�menter plusieurs m�thodes telles que 
 * <code>getInstance()</code>, <code>hasInstance()</code>, 
 * <code>releaseInstance()</code>. Elle doit �galement emp�cher que de 
 * nouvelles instances de la classes ne soient cr��es, ce qui peut �tre fait en 
 * g�n�rant une exception si les m�thodes <code>__construct()</code>, 
 * <code>__clone()</code> ou <code>__wakeup()</code> sont appell�es ou en 
 * r�duisant la visibilit� de ces m�thodes (<code>private</code> ou 
 * <code>protected</code>).
 * 
 * Pour �viter d'�tre trop "rigide", cette interface ne d�finit que la m�thode 
 * <code>getInstance()<code> qui est la seule m�thode r�ellement indispensable;
 * 
 * @package     Bdsp
 * @subpackage  core 
 */
interface Singleton
{
   /**
    * Retourne l'instance unique de la classe.
    * 
    * @return StdClass L'instance du module
    */
    public static function getInstance();
}