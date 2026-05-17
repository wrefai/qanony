<?php

namespace App\Controllers;

class LanguageController extends BaseController
{
    public function switch(string $locale)
    {
        $supported = ['ar', 'en'];

        if (in_array($locale, $supported, true)) {
            session()->set('locale', $locale);
            service('request')->setLocale($locale);
        }

        // Also handle optional ?theme= param (from old app.js fire-and-forget calls)
        $theme = $this->request->getGet('theme');
        if ($theme !== null && in_array($theme, ['light', 'dark'], true)) {
            session()->set('theme', $theme);
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
