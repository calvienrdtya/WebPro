<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Services\Ai\ChatService;
use App\Support\AiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Endpoint widget chat toko (Tanya AI). Kunci API tetap di server; widget hanya mengirim pesan dan gambar. */
class ChatController extends Controller
{
    /** Riwayat percakapan aktif untuk ditampilkan ulang saat widget dibuka (user login: lintas perangkat). */
    public function history(Request $request, ChatService $chat): JsonResponse
    {
        return response()->json(['messages' => $chat->transcript($chat->current($request->user(), $this->sessionKey($request)))]);
    }

    public function send(Request $request, ChatService $chat): JsonResponse
    {
        $data = $request->validate([
            'message' => ['nullable', 'required_without:image', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.AiSettings::advanced('chat_image_mb') * 1024],
        ], [
            'message.required_without' => 'Tulis pertanyaan atau lampirkan gambar.',
            'image.image' => 'Lampiran harus berupa gambar.',
            'image.mimes' => 'Gambar harus berformat JPG, PNG, atau WebP.',
            'image.max' => 'Ukuran gambar maksimal '.AiSettings::advanced('chat_image_mb').' MB.',
        ]);

        return response()->json($chat->send(
            $request->user(),
            $this->sessionKey($request),
            $request->ip(),
            trim((string) ($data['message'] ?? '')),
            $request->file('image'),
        ));
    }

    /** Kunci percakapan tamu, disimpan di sesi (hilang saat sesi browser berakhir). */
    private function sessionKey(Request $request): string
    {
        if (! $request->session()->has(ChatService::SESSION_KEY)) {
            $request->session()->put(ChatService::SESSION_KEY, Str::random(40));
        }

        return $request->session()->get(ChatService::SESSION_KEY);
    }
}
