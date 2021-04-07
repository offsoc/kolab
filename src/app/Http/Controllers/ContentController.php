<?php

namespace App\Http\Controllers;

class ContentController extends Controller
{
    /**
     * Get the HTML content for the specified page
     *
     * @param string $page Page template identifier
     *
     * @return \Illuminate\View\View
     */
    public function pageContent(string $page)
    {
        if (empty($page) || !preg_match('/^[a-z\/]+$/', $page)) {
            abort(404);
        }

        $page = str_replace('/', '.', $page);
        $file = sprintf('themes/%s/pages/%s.blade.php', \config('app.theme'), $page);
        $view = sprintf('%s.pages.%s', \config('app.theme'), $page);

        if (!file_exists(resource_path($file))) {
            abort(404);
        }

        return view($view)->with('env', \App\Utils::uiEnv());
    }

    /**
     * Get the list of FAQ entries for the specified page
     *
     * @param string $page Page path
     *
     * @return \Illuminate\Http\JsonResponse JSON response
     */
    public function faqContent(string $page)
    {
        if (empty($page)) {
            return $this->errorResponse(404);
        }

        $faq = [];

        $theme_name = \config('app.theme');
        $theme_file = resource_path("themes/{$theme_name}/theme.json");

        if (file_exists($theme_file)) {
            $theme = json_decode(file_get_contents($theme_file), true);
            if (json_last_error() != JSON_ERROR_NONE) {
                \Log::error("Failed to parse $theme_file: " . json_last_error_msg());
            } elseif (!empty($theme['faq']) && !empty($theme['faq'][$page])) {
                $faq = $theme['faq'][$page];
            }

            // TODO: Support pages with variables, e.g. users/<user-id>
        }

        return response()->json(['status' => 'success', 'faq' => $faq]);
    }
}
