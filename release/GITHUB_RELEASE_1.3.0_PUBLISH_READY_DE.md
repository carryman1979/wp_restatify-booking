Title: Restatify Booking Assistant 1.3.0

## Highlights

- Öffentlicher Storno-Flow mit sicheren Cancel-Tokens, Nonce-Schutz und Captcha-Bestätigung
- Stornobestätigungen für Interessenten sowie optionale interne Stornobenachrichtigungen
- Gebrandete HTML-Mail-Defaults mit Footer, Disclaimer, maschinellem Hinweis und Umwelthinweis
- Automatisches Restatify-Theme-Branding für Mail-Logo und CI-Farben, mit Plugin-Fallback wenn das Theme nicht aktiv ist
- Lokalisierte Zeitdarstellung in Buchungs- und Stornomails
- Verbesserte Backend-Fehlerabbildung, sodass FastAPI-Validierungsfehler nicht mehr als generisches "Array" erscheinen

## Kompatibilität

- Plugin-Version: `1.3.0`
- Empfohlene API-Version: `1.2.0`

## Validierung

- Lokal gegen WordPress, Booking API und Mailpit smoke-getestet
- Buchungsbestätigung, Stornobestätigung und interne Stornobenachrichtigung lokal verifiziert
- Release-Archiv neu gebaut und darauf geprüft, dass Git-Metadaten und verschachtelte Release-Artefakte ausgeschlossen sind

## Enthaltenes Artefakt

- `wp-restatify-booking-assistant-1.3.0.zip`