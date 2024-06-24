# PHP Text To Speech

## How it works ?

---

This library leverages Google Translate's capabilities to provide text-to-speech functionality. Despite its unconventional approach, it has been reliable for years.

Google Translate can handle millions of requests, so you can use this library confidently.

1. The library sends a standard request to the Google Translate page, including query parameters with the text that needs to be converted.

2. The request headers are configured to instruct Google Translate to respond with the audio data in a base64-encoded format. This is necessary because PHP cannot natively handle audio formats.

3. The base64-encoded audio data is then handed over to JavaScript, which decodes it into an audio format that can be played.

> Please pay attention, for some reason maybe your ip will be blocked. but idk.

## Install

```console
composer require ibroid/php-tts
```
