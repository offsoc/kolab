<?php

namespace App\Http\Controllers\API\V4;

use App\Backends\Storage;
use App\Fs\Item;
use App\Fs\Property;
use App\Http\Controllers\RelationController;
use App\Rules\FileName;
use App\User;
use App\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class FilesController extends RelationController
{
    protected const READ = 'r';
    protected const WRITE = 'w';

    /** @var string Resource localization label */
    protected $label = 'file';

    /** @var string Resource model name */
    protected $model = Item::class;


    /**
     * Delete a file.
     *
     * @param string $id File identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function destroy($id)
    {
        // Only the file owner can do that, for now
        $file = $this->inputFile($id, null);

        if (is_int($file)) {
            return $this->errorResponse($file);
        }

        // Here we're just marking the file as deleted, it will be removed from the
        // storage later with the fs:expunge command
        $file->delete();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.file-delete-success'),
        ]);
    }

    /**
     * Fetch content of a file.
     *
     * @param string $id The download (not file) identifier.
     *
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function download($id)
    {
        $fileId = Cache::get('download:' . $id);

        if (!$fileId) {
            return response('Not found', 404);
        }

        $file = Item::find($fileId);

        if (!$file) {
            return response('Not found', 404);
        }

        return Storage::fileDownload($file);
    }

    /**
     * Fetch the permissions for the specific file.
     *
     * @param string $fileId The file identifier.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPermissions($fileId)
    {
        // Only the file owner can do that, for now
        $file = $this->inputFile($fileId, null);

        if (is_int($file)) {
            return $this->errorResponse($file);
        }

        $result = $file->properties()->where('key', 'like', 'share-%')->get()->map(
            fn($prop) => self::permissionToClient($prop->key, $prop->value)
        );

        $result = [
            'list' => $result,
            'count' => count($result),
        ];

        return response()->json($result);
    }

    /**
     * Add permission for the specific file.
     *
     * @param string $fileId The file identifier.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createPermission($fileId)
    {
        // Only the file owner can do that, for now
        $file = $this->inputFile($fileId, null);

        if (is_int($file)) {
            return $this->errorResponse($file);
        }

        // Validate/format input
        $v = Validator::make(request()->all(), [
                'user' => 'email|required',
                'permissions' => 'string|required',
        ]);

        $errors = $v->fails() ? $v->errors()->toArray() : [];

        $acl = self::inputAcl(request()->input('permissions'));

        if (empty($errors['permissions']) && empty($acl)) {
            $errors['permissions'] = \trans('validation.file-perm-invalid');
        }

        $user = \strtolower(request()->input('user'));

        // Check if it already exists
        if (empty($errors['user'])) {
            if ($file->properties()->where('key', 'like', 'share-%')->where('value', 'like', "$user:%")->exists()) {
                $errors['user'] = \trans('validation.file-perm-exists');
            }
        }

        if (!empty($errors)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        // Create the property (with a unique id)
        while ($shareId = 'share-' . \App\Utils::uuidStr()) {
            if (!Property::where('key', $shareId)->exists()) {
                break;
            }
        }

        $file->setProperty($shareId, "$user:$acl");

        $result = self::permissionToClient($shareId, "$user:$acl");

        return response()->json($result + [
                'status' => 'success',
                'message' => \trans('app.file-permissions-create-success'),
        ]);
    }

    /**
     * Delete file permission.
     *
     * @param string $fileId The file identifier.
     * @param string $id     The file permission identifier.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deletePermission($fileId, $id)
    {
        // Only the file owner can do that, for now
        $file = $this->inputFile($fileId, null);

        if (is_int($file)) {
            return $this->errorResponse($file);
        }

        $property = $file->properties()->where('key', $id)->first();

        if (!$property) {
            return $this->errorResponse(404);
        }

        $property->delete();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.file-permissions-delete-success'),
        ]);
    }

    /**
     * Update file permission.
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @param string                   $fileId  The file identifier.
     * @param string                   $id      The file permission identifier.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePermission(Request $request, $fileId, $id)
    {
        // Only the file owner can do that, for now
        $file = $this->inputFile($fileId, null);

        if (is_int($file)) {
            return $this->errorResponse($file);
        }

        $property = $file->properties()->where('key', $id)->first();

        if (!$property) {
            return $this->errorResponse(404);
        }

        // Validate/format input
        $v = Validator::make($request->all(), [
                'user' => 'email|required',
                'permissions' => 'string|required',
        ]);

        $errors = $v->fails() ? $v->errors()->toArray() : [];

        $acl = self::inputAcl($request->input('permissions'));

        if (empty($errors['permissions']) && empty($acl)) {
            $errors['permissions'] = \trans('validation.file-perm-invalid');
        }

        $user = \strtolower($request->input('user'));

        if (empty($errors['user']) && strpos($property->value, "$user:") !== 0) {
            if ($file->properties()->where('key', 'like', 'share-%')->where('value', 'like', "$user:%")->exists()) {
                $errors['user'] = \trans('validation.file-perm-exists');
            }
        }

        if (!empty($errors)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        $property->value = "$user:$acl";
        $property->save();

        $result = self::permissionToClient($property->key, $property->value);

        return response()->json($result + [
                'status' => 'success',
                'message' => \trans('app.file-permissions-update-success'),
        ]);
    }

    /**
     * Listing of files (and folders).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $search = trim(request()->input('search'));
        $page = intval(request()->input('page')) ?: 1;
        $pageSize = 100;
        $hasMore = false;

        $user = $this->guard()->user();

        $result = $user->fsItems()->select('fs_items.*', 'fs_properties.value as name')
            ->join('fs_properties', 'fs_items.id', '=', 'fs_properties.item_id')
            ->whereNot('type', '&', Item::TYPE_INCOMPLETE)
            ->where('key', 'name');

        if (strlen($search)) {
            $result->whereLike('fs_properties.value', $search);
        }

        $result = $result->orderBy('name')
            ->limit($pageSize + 1)
            ->offset($pageSize * ($page - 1))
            ->get();

        if (count($result) > $pageSize) {
            $result->pop();
            $hasMore = true;
        }

        // Process the result
        $result = $result->map(
            function ($file) {
                $result = $this->objectToClient($file);
                $result['name'] = $file->name; // @phpstan-ignore-line

                return $result;
            }
        );

        $result = [
            'list' => $result,
            'count' => count($result),
            'hasMore' => $hasMore,
        ];

        return response()->json($result);
    }

    /**
     * Fetch the specific file metadata or content.
     *
     * @param string $id The file identifier.
     *
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function show($id)
    {
        $file = $this->inputFile($id, self::READ);

        if (is_int($file)) {
            return $this->errorResponse($file);
        }

        $response = $this->objectToClient($file, true);

        if (request()->input('downloadUrl')) {
            // Generate a download URL (that does not require authentication)
            $downloadId = Utils::uuidStr();
            Cache::add('download:' . $downloadId, $file->id, 60);
            $response['downloadUrl'] = Utils::serviceUrl('api/v4/files/downloads/' . $downloadId);
        } elseif (request()->input('download')) {
            // Return the file content
            return Storage::fileDownload($file);
        }

        $response['mtime'] = $file->updated_at->format('Y-m-d H:i');

        // TODO: Handle read-write/full access rights
        $isOwner = $this->guard()->user()->id == $file->user_id;
        $response['canUpdate'] = $isOwner;
        $response['canDelete'] = $isOwner;
        $response['isOwner'] = $isOwner;

        return response()->json($response);
    }

    /**
     * Create a new file.
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function store(Request $request)
    {
        $user = $this->guard()->user();

        // Validate file name input
        $v = Validator::make($request->all(), ['name' => ['required', new FileName($user)]]);

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $filename = $request->input('name');
        $media = $request->input('media');

        // FIXME: Normally people just drag and drop/upload files.
        // The client side will not know whether the file with the same name
        // already exists or not. So, in such a case should we throw
        // an error or accept the request as an update?

        $params = [];

        if ($media == 'resumable') {
            $params['uploadId'] = 'resumable';
            $params['size'] = $request->input('size');
            $params['from'] = $request->input('from') ?: 0;
        }

        // TODO: Delete the existing incomplete file with the same name?

        $file = $user->fsItems()->create(['type' => Item::TYPE_INCOMPLETE | Item::TYPE_FILE]);
        $file->setProperty('name', $filename);

        try {
            $response = Storage::fileInput($request->getContent(true), $params, $file);

            $response['status'] = 'success';

            if (!empty($response['id'])) {
                $response += $this->objectToClient($file, true);
                $response['message'] = \trans('app.file-create-success');
            }
        } catch (\Exception $e) {
            \Log::error($e);
            $file->delete();
            return $this->errorResponse(500);
        }

        return response()->json($response);
    }

    /**
     * Update a file.
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @param string                   $id      File identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function update(Request $request, $id)
    {
        $file = $this->inputFile($id, self::WRITE);

        if (is_int($file)) {
            return $this->errorResponse($file);
        }

        $media = $request->input('media') ?: 'metadata';

        if ($media == 'metadata') {
            $filename = $request->input('name');

            // Validate file name input
            if ($filename != $file->getProperty('name')) {
                $v = Validator::make($request->all(), ['name' => [new FileName($file->user)]]);

                if ($v->fails()) {
                    return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
                }

                $file->setProperty('name', $filename);
            }

            // $file->save();
        } elseif ($media == 'resumable' || $media == 'content') {
            $params = [];

            if ($media == 'resumable') {
                $params['uploadId'] = 'resumable';
                $params['size'] = $request->input('size');
                $params['from'] = $request->input('from') ?: 0;
            }

            try {
                $response = Storage::fileInput($request->getContent(true), $params, $file);
            } catch (\Exception $e) {
                \Log::error($e);
                return $this->errorResponse(500);
            }
        } else {
            $errors = ['media' => \trans('validation.entryinvalid', ['attribute' => 'media'])];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        $response['status'] = 'success';

        if ($media == 'metadata' || !empty($response['id'])) {
            $response += $this->objectToClient($file, true);
            $response['message'] = \trans('app.file-update-success');
        }

        return response()->json($response);
    }

    /**
     * Upload a file content.
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @param string                   $id      Upload (not file) identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function upload(Request $request, $id)
    {
        $params = [
            'uploadId' => $id,
            'from' => $request->input('from') ?: 0,
        ];

        try {
            $response = Storage::fileInput($request->getContent(true), $params);

            $response['status'] = 'success';

            if (!empty($response['id'])) {
                $response += $this->objectToClient(Item::find($response['id']), true);
                $response['message'] = \trans('app.file-upload-success');
            }
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->errorResponse(500);
        }

        return response()->json($response);
    }

    /**
     * Convert Permission to an array for the API response.
     *
     * @param string $id    Permission identifier
     * @param string $value Permission record
     *
     * @return array Permission data
     */
    protected static function permissionToClient(string $id, string $value): array
    {
        list($user, $acl) = explode(':', $value);

        $perms = strpos($acl, self::WRITE) !== false ? 'read-write' : 'read-only';

        return [
            'id' => $id,
            'user' => $user,
            'permissions' => $perms,
            'link' => Utils::serviceUrl('file/' . $id),
        ];
    }

    /**
     * Convert ACL label into internal permissions spec.
     *
     * @param string $acl Access rights label
     *
     * @return ?string Permissions ('r' or 'rw')
     */
    protected static function inputAcl($acl): ?string
    {
        // The ACL widget supports 'full', 'read-write', 'read-only',
        if ($acl == 'read-write') {
            return self::READ . self::WRITE;
        }

        if ($acl == 'read-only') {
            return self::READ;
        }

        return null;
    }

    /**
     * Get the input file object, check permissions
     *
     * @param string  $fileId     File or file permission identifier
     * @param ?string $permission Required access rights
     *
     * @return \App\Fs\Item|int File object or error code
     */
    protected function inputFile($fileId, $permission)
    {
        $user = $this->guard()->user();
        $isShare = str_starts_with($fileId, 'share-');

        // Access via file permission identifier
        if ($isShare) {
            $property = Property::where('key', $fileId)->first();

            if (!$property) {
                return 404;
            }

            list($acl_user, $acl) = explode(':', $property->value);

            if (!$permission || $acl_user != $user->email || strpos($acl, $permission) === false) {
                return 403;
            }

            $fileId = $property->item_id;
        }

        $file = Item::find($fileId);

        if (!$file) {
            return 404;
        }

        if (!$isShare && $user->id != $file->user_id) {
            return 403;
        }

        return $file;
    }

    /**
     * Prepare a file object for the UI.
     *
     * @param object $object An object
     * @param bool   $full   Include all object properties
     *
     * @return array Object information
     */
    protected function objectToClient($object, bool $full = false): array
    {
        $result = ['id' => $object->id];

        if ($full) {
            $props = array_filter($object->getProperties(['name', 'size', 'mimetype']));

            // convert size to int and make sure the property exists
            $props['size'] = (int) ($props['size'] ?? 0);
            $result += $props;
        }

        return $result;
    }
}
