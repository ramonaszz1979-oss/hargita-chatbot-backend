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

## 3) A chatbot elhelyezése az oldalon
1. Nyisd meg azt az oldalt vagy bejegyzést, ahol meg szeretnéd jeleníteni a chatbotot.
2. Illeszd be a következő shortcode-ot a tartalomba: `[simple_chatbot]`.
3. Mentés vagy frissítés után az oldalon megjelenik a chatbot felület a **Beállítások** és **Preview** gombokkal.

## 4) Beállítások (vizuálisan, az oldalon)
1. Nyisd meg az oldalt látogatói nézetben (admin jogosultsággal bejelentkezve). A chatbot felett kattints a **Beállítások** gombra.
2. A megjelenő felületen add meg a chatbot címsorát, az **OpenAI API-kulcsot** (pl. `sk-...`) és a **chatbot viselkedését** (rendszerutasítás, pl. „barátságos, rövid válaszok”).
3. A **Tudásanyag fájlok** résznél tölts fel TXT, MD vagy PDF fájlokat; tartalmuk bekerül az AI kontextusába.
4. A **Tudásanyag weboldalak** űrlapon adj meg kezdőoldali URL-eket (pl. `https://pelda.hu`). A bővítmény a főoldal mellett a belső aloldalak szövegét is begyűjti, `.txt` fájlokba menti a WordPress feltöltések mappájában (`wp-content/uploads/simple-chatbot/url-cache`), és ezekből tölti be a tudásanyagot az AI-nak.
5. Mentés után a **Preview** gombbal azonnal kipróbálhatod, hogyan reagál a chatbot az új beállításokkal és tudásanyaggal.

## 5) Működés tesztelése
1. Nyisd meg az oldalt látogatói nézetben.
2. Írj egy üzenetet az input mezőbe, majd kattints a **Küldés** gombra.
3. Ha be van állítva az OpenAI API-kulcs, a chatbot az OpenAI Chat Completions API-n keresztül kéri le a választ, és megjeleníti azt az üzenetlista alatt.

## Tippek
- Ha a megjelenést szeretnéd módosítani, szerkeszd a `assets/chatbot.css` fájlt.
- A válaszlogika a `chatbot-plugin.php` `generate_response()` függvényében található, amely az OpenAI Chat Completions végpontot hívja meg a beállított API-kulccsal.
