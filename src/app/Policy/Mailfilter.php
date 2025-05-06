<?php

namespace App\Policy;

use App\Policy\Mailfilter\MailParser;
use App\Policy\Mailfilter\Modules;
use App\Policy\Mailfilter\Result;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Mailfilter
{
    /**
     * SMTP Content Filter
     *
     * @param Request $request the API request
     *
     * @return Response|StreamedResponse The response
     */
    public static function handle(Request $request)
    {
        // How big file we can handle depends on the method. We support both: 1) passing
        // file in the request body, or 2) using multipart/form-data method (standard file upload).
        // 1. For the first case maximum size is defined by:
        //    - w/ Swoole: package_max_length in config/octane.php,
        //      In this case Swoole needs twice as much memory as Laravel w/o Octane
        //      (https://github.com/laravel/octane/issues/959), e.g. 10 MB file under Octane will need 20MB
        //      plus the memory allocated initially (~22MB) = ~42MB
        //      So, to handle 50MB email message we need ~125MB memory (w/o Swoole it'll be ~55MB)
        //      Note: This does not yet consider parsing/modifying the content, but outputing the content
        //      back itself does not require any extra memory.
        //    - w/o Swoole: post_max_size in php.ini.
        // 2. For the second case maximum size is defined by upload_max_filesize in config/octane.php or php.ini.
        //    In this case temp files are used no matter it's under Swoole or not, i.e. memory limit is not an issue.
        //    PHP's post_max_size have to be equal or greater for the w/o Swoole case.

        // TODO: As a performance optimization... Not all mail bodies will need to be parsed.
        // We should consider doing two requests. In first we'd send only mail headers,
        // then we'd send body in another request, but only if needed. For example, a text/plain
        // message from same domain sender does not include an iTip, nor needs a footer injection.

        $files = $request->allFiles();
        if (count($files) == 1) {
            $file = $files[array_key_first($files)];
            if (!$file->isValid()) {
                return response('Invalid file upload', 500);
            }

            $stream = fopen($file->path(), 'r');
        } else {
            $stream = $request->getContent(true);
        }

        $parser = new MailParser($stream);

        if ($recipient = $request->recipient) {
            $parser->setRecipient($recipient);
        }

        if ($sender = $request->sender) {
            $parser->setSender($sender);
        }

        // TODO: The list of modules and their config will come from somewhere
        $modules = [
            'itip' => Modules\ItipModule::class,
            'external-sender' => Modules\ExternalSenderModule::class,
        ];

        foreach ($modules as $module) {
            $engine = new $module();

            $result = $engine->handle($parser);

            if ($result) {
                if ($result->getStatus() == Result::STATUS_REJECT) {
                    // FIXME: Better code? Should we use custom header instead?
                    return response('', 460);
                }
                if ($result->getStatus() == Result::STATUS_DISCARD) {
                    // FIXME: Better code? Should we use custom header instead?
                    return response('', 461);
                }
            }
        }

        // If mail content has been modified, stream it back to Postfix
        if ($parser->isModified()) {
            $response = new StreamedResponse();

            $response->headers->replace([
                'Content-Type' => 'message/rfc822',
                'Content-Disposition' => 'attachment',
            ]);

            $stream = $parser->getStream();

            $response->setCallback(static function () use ($stream) {
                fpassthru($stream);
                fclose($stream);
            });

            return $response;
        }

        return response('', 204);
    }
}
