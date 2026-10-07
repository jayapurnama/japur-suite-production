# JaPur Remote Share Receiver 0.1.0

Companion Android receiver untuk membuat JaPur Remote muncul langsung di Android Sharesheet untuk image/*.

## Build
GitHub Actions builds a debug APK automatically. The artifact is published as `japur-remote-share-receiver-debug`.

## Setup
1. Install the JaPur Web Remote Share Bridge plugin update.
2. Open WordPress → JaPur Web Remote → Pengaturan.
3. Copy Kunci Share Android.
4. Install the generated APK.
5. Open the app once and enter the JaPur Remote URL and Share Key.
6. Test ChatGPT → Share image → JaPur Remote.

## Payload
Handles ACTION_SEND/ACTION_SEND_MULTIPLE image/*, EXTRA_STREAM, and EXTRA_TEXT.
The image is uploaded over HTTPS to the JaPur WordPress endpoint and the returned URL is forwarded to the JaPur Remote PWA.

## Permission
Only INTERNET is declared; no broad storage/photo permission is requested.