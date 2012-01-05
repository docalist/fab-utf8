<?php

/**
 * Cette classe impl�mente un mod�le de (non-)s�curit� trivial.
 *
 * L'utilisateur :
 *
 * - est toujours anonyme
 *
 * - est toujours connect� (isConnected retourne toujours true)
 *
 * - a tous les droits (hasRight retourne toujours true)
 */
class NoSecurity extends BaseSecurity
{
    /**
     * Teste si l'utilisateur dispose du droit unique indiqu�.
     *
     * @return true Retourne toujours true.
     */
    public function hasRight($right) // TODO: est-ce que ce code (ou une partie) ne devrait pas �tre plut�t dans User?
    {
        return true;
    }
}
?>
