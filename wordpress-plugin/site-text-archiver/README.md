# Site Text Archiver telepítési és használati útmutató

## Telepítés WordPressbe
1. Másold a `site-text-archiver` mappát a WordPress `wp-content/plugins/` könyvtárába.
2. Jelentkezz be a WordPress admin felületére, majd a **Bővítmények** menüben aktiváld a **Site Text Archiver** bővítményt.
3. Az adminfelületen a **Eszközök → Site Text Archiver** menüpont alatt felvehetsz több weboldalt (dinasztikus „+” gombbal) és indíthatod a letöltést, vagy csak elmentheted a listát. A felületen továbbra is megtalálod a külső tartalomarchiváló oldalra mutató gombot (https://hargita.smartonlineedu.com/site-arhiver/), ha azt szeretnéd megnyitni. A lap alján listát látsz az eddig letöltött weboldal-mappákról, ahol a webcímre kattintva megnyithatod az eredeti oldalt, és egyenként törölheted az archívumokat.

## Frontend (menüpontos) használat
Ha egy menüpontos oldalról szeretnéd megnyitni az archiváló felületet, így állítsd be:
1. Készíts egy új oldalt a WordPressben (pl. „Tartalom archiválás” címmel).
2. Illeszd be az oldal tartalmába a következő rövidkódot:

   ```
   [site_text_archiver_form]
   ```

3. Mentsd el az oldalt, majd tedd be a menübe (Megjelenés → Menük). A bejelentkezett és **rendszergazda jogosultságú** felhasználók a frontend űrlapon is felvehetik az URL-eket, majd elindíthatják vagy elmenthetik azokat, és ugyanitt megnyithatják a külső tartalomarchiváló oldalt is.

## Működés
- Az admin és a frontend nézetben dinamikus URL-mezőket kapsz (új sor hozzáadása „+” gombbal, törlés „×”-szel), amelyeket menthetsz vagy közvetlenül futtathatsz a **Letöltés indítása** gombbal. Ha egy már korábban letöltött weboldalt adsz meg, a mező alatt jelölőnégyzetet kapsz, amellyel eldöntheted, hogy a régi fájlokat törölje-e új letöltés előtt.
- Az oldal alján a meglévő archívumok táblázatban jelennek meg (webcím linkkel, fájlok száma, elérési út), és mindegyik mellett ott a **Törlés** gomb az adott weboldal teljes archívumának eltávolításához. A régiek megmaradnak, amíg kifejezetten nem kéred a törlésüket.
- A korábban letöltött, HTML-ből tisztított szöveges fájlokat továbbra is a `wp-content/uploads/site-text-archives/<domain>/` mappában találod; törléskor a megfelelő mappát és a kapcsolódó metaadatot a bővítmény automatikusan eltávolítja. Az oldalszám nincs korlátozva, minden bejárt aloldal mentésre kerül.

## Technikai megjegyzés
Ez a bővítmény tisztán PHP-alapú; nincs szükség külön Python-kiszolgálóra vagy -függőségekre.
A korábban mellékelt Python-fájlok (pl. `Dockerfile`, `Procfile`, `app.py`, `requirements.txt`) törlésre kerültek.
