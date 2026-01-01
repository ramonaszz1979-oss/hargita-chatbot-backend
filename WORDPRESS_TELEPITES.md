# Egyszerű Chatbot Készítő bővítmény telepítése WordPressre

Az alábbi lépések végigvezetnek azon, hogyan töltsd fel és aktiváld a PHP-alapú chatbot bővítményt egy WordPress weboldalon.

## 1) A bővítmény előkészítése
1. Töltsd le vagy másold ki a `wordpress-chatbot-plugin` mappát a számítógépedre.
2. Csomagold be ZIP állományba (például: `zip -r egyszeru-chatbot.zip wordpress-chatbot-plugin`).

## 2) Feltöltés az admin felületen keresztül (klasszikus plugin út)
1. Jelentkezz be a WordPress admin felületére.
2. A bal oldali menüben válaszd a **Bővítmények → Új hozzáadása** menüpontot.
3. Kattints a **Bővítmény feltöltése** gombra, majd tallózd ki az előző lépésben készített ZIP fájlt.
4. Nyomd meg a **Telepítés most** gombot, majd a telepítés után kattints az **Aktiválás** gombra.

## 2/B) Feltöltés közvetlenül a `public_html` alá (plugin feltöltés nélkül)
1. FTP/SFTP-n vagy fájlkezelőn keresztül hozd létre a `public_html/simple-chatbot` mappát.
2. Másold a teljes `wordpress-chatbot-plugin` tartalmát ebbe a mappába (a `chatbot-plugin.php`, `assets`, `includes` stb. könyvtárakkal együtt).
3. A `public_html/wp-content/mu-plugins/` mappába másold be a repóban található `wordpress-chatbot-plugin/simple-chatbot-loader.php` fájlt, és nevezd át tetszőlegesen (pl. `simple-chatbot.php`). Ha nincs `mu-plugins` mappa, hozd létre.
4. Ezzel a MU-plugin loader automatikusan betölti a `public_html/simple-chatbot/chatbot-plugin.php` állományt, így nincs szükség admin oldali plugin-feltöltésre vagy aktiválásra.
5. Lépj az oldaladra és frissíts: a `[simple_chatbot]` shortcode-ot tartalmazó oldal betöltésekor a chatbot minden funkciója elérhető marad.

## 3) A chatbot elhelyezése az oldalon
1. Nyisd meg azt az oldalt vagy bejegyzést, ahol meg szeretnéd jeleníteni a chatbotot.
2. Illeszd be a következő shortcode-ot a tartalomba: `[simple_chatbot]`.
3. Mentés vagy frissítés után az oldalon megjelenik a chatbot felület a **Beállítások** és **Preview** gombokkal.

## 3/B) Globális beágyazás (footer kóddal, hogy minden oldalon fusson)
- A bővítmény tartalmaz egy beágyazható nézetet a `?simple_chatbot_embed=1` URL-en, amit egy iframe-ben hívhatsz meg bármelyik aloldalon.
- Ha azt szeretnéd, hogy a chatbot minden oldalon megjelenjen (pl. a láblécbe illesztve), tedd a következő kódot a sablonod `footer.php` állományába **vagy** egy Egyéni HTML/Fejrész–Lábléc kódrészletbe:

```html
<style>
  .simple-chatbot-floating-frame {
    position: fixed;
    bottom: 16px;
    right: 16px;
    width: 380px;
    max-width: 90vw;
    height: 520px;
    max-height: 80vh;
    border: 0;
    border-radius: 12px;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2);
    z-index: 9999;
    overflow: hidden;
  }
</style>
<iframe
  class="simple-chatbot-floating-frame"
  src="https://sajat-domened.hu/?simple_chatbot_embed=1"
  title="Chatbot"
  loading="lazy"
></iframe>
```

- Cseréld ki a `https://sajat-domened.hu/` részt a saját weboldalad címére.
- Így a chatbot minden oldaladon betöltődik, nem kell egyenként shortcode-ot beszúrni.

## 4) Beállítások (vizuálisan, az oldalon)
1. Nyisd meg az oldalt látogatói nézetben (admin jogosultsággal bejelentkezve). A chatbot felett kattints a **Beállítások** gombra.
2. A megjelenő felületen add meg a chatbot címsorát, az **OpenAI API-kulcsot** (pl. `sk-...`), a **chatbot viselkedését** (rendszerutasítás, pl. „barátságos, rövid válaszok”), valamint a **beköszönő üzenetet** (pl. „Chatbot bekapcsolva. Írj egy kérdést!”), ami az első üzenetként jelenik meg a chat ablakban.
3. A **Tudásanyag fájlok** résznél tölts fel TXT, MD vagy PDF fájlokat; tartalmuk bekerül az AI kontextusába, és a szövegkivonat ugyanabba a gyökérbe kerül elmentésre (`wp-content/uploads/simple-chatbot/url-cache`).
4. A **Weboldalak** fülön a **Site archív** gomb megnyitja a `https://hargita.smartonlineedu.com/site-arhiver/` oldalt. A chatbot elsőként a `wp-content/uploads/site-text-archives` könyvtárból olvassa be a `.txt` állományokat tudásanyagnak; ha ott nincs tartalom, a Site archív oldalról próbál szöveget letölteni és az AI kontextusába illeszteni.
5. A **Folyamat szerkesztő** menüben szekciókat és „Válasz lehetőség” pontokat vihetsz fel (pl. „Szia, én Hargita megye…” szekció, azon belül „Látnivalók”, „Szállás”, „Egyéb” pontok). A szekcióhoz opcionálisan adhatsz **űrlap linket** és **gombfeliratot**: a látogatók a chatbotban a megadott feliratú gombot látják, ami új lapon nyitja meg az űrlapot. A folyamatok a `wp-content/uploads/simple-chatbot/processes.json` fájlba mentődnek, és automatikusan bekerülnek az AI kontextusába.
6. Ha a „betanított” űrlapot egy másik oldalon, felhasználói nézetben szeretnéd megjeleníteni: (1) azon az oldalon is helyezd el a `[simple_chatbot]` shortcode-ot, (2) a Folyamat szerkesztőben add meg az űrlap linkjét és gombfeliratát, (3) frissítés után a látogatók az adott oldalon megjelenő chatbotban látják és kattinthatják az űrlap gombot, ami új lapon nyílik meg.
7. Mentés után a **Preview** gombbal azonnal kipróbálhatod, hogyan reagál a chatbot az új beállításokkal és tudásanyaggal.
8. Az **Általános** fülön a **Beágyazási kód megnyitása** gomb egy másolható iframe-snippetet mutat, amit a láblécbe vagy globális HTML blokkba illesztve minden oldalon megjelenítheted a chatbotot.

## 5) Működés tesztelése
1. Nyisd meg az oldalt látogatói nézetben.
2. Írj egy üzenetet az input mezőbe, majd kattints a **Küldés** gombra.
3. Ha be van állítva az OpenAI API-kulcs, a chatbot az OpenAI Chat Completions API-n keresztül kéri le a választ, és megjeleníti azt az üzenetlista alatt.

## Tippek
- Ha a megjelenést szeretnéd módosítani, szerkeszd a `assets/chatbot.css` fájlt.
- A válaszlogika a `chatbot-plugin.php` `generate_response()` függvényében található, amely az OpenAI Chat Completions végpontot hívja meg a beállított API-kulccsal.
