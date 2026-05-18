<?php

namespace App\Controllers;

class LanguageController extends BaseController
{
    public function switch(string $locale)
    {
        $supported = ['ar', 'en'];

        if (in_array($locale, $supported, true)) {
            session()->set('locale', $locale);
        }

        // Also handle optional ?theme= param (from old app.js fire-and-forget calls)
        $theme = $this->request->getGet('theme');
        if ($theme !== null && in_array($theme, ['light', 'dark'], true)) {
            session()->set('theme', $theme);
        }

        // Force-commit the session write & release the lock before issuing the
        // redirect. Without this, CI4's DatabaseHandler writes the session row
        // during request shutdown — and the browser's redirected request can
        // hit the server (and read the session) BEFORE that write commits, so
        // the new locale appears to "not stick". CI4's Session class has no
        // close() method, so call the native PHP function which is always
        // available and flushes whatever session_start() opened.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        return redirect()->back();
    }

    /**
     * AJAX endpoint: persist user's chosen theme to session.
     * Called by app.js as a fire-and-forget GET request.
     */
    public function setTheme()
    {
        $theme = $this->request->getGet('theme');
        if (in_array($theme, ['light', 'dark'], true)) {
            session()->set('theme', $theme);
        }

        // Minimal JSON response (caller ignores it)
        return $this->response
            ->setContentType('application/json')
            ->setBody('{"ok":true}');
    }
}
