# Egyszerű Chatbot Készítő bővítmény telepítése WordPressre

Az alábbi lépések végigvezetnek azon, hogyan töltsd fel és aktiváld a PHP-alapú chatbot bővítményt egy WordPress weboldalon.

## 1) A bővítmény előkészítése
1. Töltsd le vagy másold ki a `wordpress-chatbot-plugin` mappát a számítógépedre.
2. Csomagold be ZIP állományba (például: `zip -r egyszeru-chatbot.zip wordpress-chatbot-plugin`).

## 2) Feltöltés az admin felületen keresztül
1. Jelentkezz be a WordPress admin felületére.
2. A bal oldali menüben válaszd a **Bővítmények → Új hozzáadása** menüpontot.
3. Kattints a **Bővítmény feltöltése** gombra, majd tallózd ki az előző lépésben készített ZIP fájlt.
4. Nyomd meg a **Telepítés most** gombot, majd a telepítés után kattints az **Aktiválás** gombra.

## 3) Beállítások
1. Aktiválás után a **Beállítások → Chatbot** menüben elérhetővé válik a bővítmény saját oldala.
2. Itt megadhatod a chatbot címsorát, amely a widget tetején jelenik meg.
3. Adj meg egy **OpenAI API-kulcsot** (pl. `sk-...`), hogy a chatbot valós AI válaszokat tudjon kérni a Chat Completions végponttól.
4. A **Chatbot viselkedés (rendszerutasítás)** mezőben szabhatod testre a hangnemet és a stílust (pl. formális, barátságos, rövid válaszok).
5. Ha saját tudásanyagot szeretnél adni a chatbotnak, a **Tudásanyag feltöltése** résznél tölts fel egy TXT, MD vagy PDF fájlt. A feltöltött fájlok tartalma bekerül az AI kontextusába.
6. Mentéshez kattints a **Módosítások mentése** gombra.

## 4) A chatbot elhelyezése az oldalon
1. Nyisd meg azt az oldalt vagy bejegyzést, ahol meg szeretnéd jeleníteni a chatbotot.
2. Illeszd be a következő shortcode-ot a tartalomba: `[simple_chatbot]`.
3. Mentés vagy frissítés után az oldalon megjelenik a chatbot felület.

## 5) Működés tesztelése
1. Nyisd meg az oldalt látogatói nézetben.
2. Írj egy üzenetet az input mezőbe, majd kattints a **Küldés** gombra.
3. Ha be van állítva az OpenAI API-kulcs, a chatbot az OpenAI Chat Completions API-n keresztül kéri le a választ, és megjeleníti azt az üzenetlista alatt.

## Tippek
- Ha a megjelenést szeretnéd módosítani, szerkeszd a `assets/chatbot.css` fájlt.
- A válaszlogika a `chatbot-plugin.php` `generate_response()` függvényében található, amely az OpenAI Chat Completions végpontot hívja meg a beállított API-kulccsal.
