# PHP Text To Speech

## How it works ?

---

This library leverages Google Translate's capabilities to provide text-to-speech functionality. Despite its unconventional approach, it has been reliable for years.

Google Translate can handle millions of requests, so you can use this library confidently.

1. The library sends a standard request to the Google Translate page, including query parameters with the text that needs to be converted.

2. The request headers are configured to instruct Google Translate to respond with the audio data in a base64-encoded format. This is necessary because PHP cannot natively handle audio formats.

3. The base64-encoded audio data is then handed over to JavaScript, which decodes it into an audio format that can be played.

## Install

#### Requirements

- PHP >= 7.4

```console
composer require ibroid/php-tts:dev-master
```

## Example

```html
<form onsubmit="sendText(event)" action="/index.php" method="post">
  <input required type="text" name="text" placeholder="Type any words" />
  <button>Play Audio</button>
  <h2 id="indicator">Status : Waiting for request</h2>
  <div id="output"></div>
</form>
```

```javascript
function sendText(event) {
  event.preventDefault();
  document.getElementById("indicator").innerText = "Status : Loading...";

  const body = new FormData();
  body.append("text", event.target.text.value);

  fetch("/index.php", {
    method: "POST",
    body: body,
  })
    .then(async (response) => {
      document.getElementById("indicator").innerText = "Status : Playing";

      const audio = new Audio(
        "data:audio/wav;base64," + (await response.text())
      );

      audio.addEventListener("ended", () => {
        document.getElementById("indicator").innerText = "Status : Ended";
      });

      const audioElement = document.createElement("audio");
      audioElement.src = audio.src;
      audioElement.controls = true;
      document.getElementById("output").append(audioElement);

      audio.play();
    })

    .catch((err) => {
      document.getElementById("indicator").innerText = "Status : Error. " + err;
    });
}
```

```php
include "./vendor/autoload.php";

use Ibroid\PhpTts\Tts as Tts;

if (isset($_POST['text'])) {
  $audio = Tts::generateAudio($_POST['text'], [
    "lang" => "en",
    "timeout" => 5000
  ]);

  echo $audio;
}

```
