# File Text Archive

Egy egyszerű WordPress-bővítmény, amellyel tetszőleges dokumentumot (PDF, DOCX, TXT, képek stb.) tölthetsz fel, detektálja a nyelvet és TXT-változatot ment az `uploads/site-text-archives/file` mappába.

## Rövidkód használata
A feltöltő és lista felületet bármelyik oldalba vagy bejegyzésbe beillesztheted a következő shortcode-dal:

```
[file_text_archive]
```

A shortcode csak olyan felhasználóknak jelenít meg feltöltőmezőt, akik rendelkeznek feltöltési jogosultsággal.

## Telepítés
1. Csomagold a `wordpress-plugin/` mappát ZIP-be (pl. `zip -r file-text-archive.zip wordpress-plugin`).
2. A WordPress adminban válaszd a **Bővítmények → Új hozzáadása → Bővítmény feltöltése** menüpontot.
3. Tallózd be a ZIP-et, majd telepítsd és aktiváld a bővítményt.

Aktiválás után két helyen is eléred a felületet:
- **Saját menüpont**: **Text Archive** a bal oldali admin menüben.
- **Eszközök menü**: **Eszközök → File Text Archive**, ahol ugyanaz a feltöltő és lista felület elérhető.

## Hibaelhárítás
- PDF-ek esetén a pontos szövegkinyeréshez telepítsd a `pdftotext` eszközt (pl. `poppler-utils`).
- Törléskor a bővítmény az eredeti fájlt és a generált TXT-t is eltávolítja az archívumból.
