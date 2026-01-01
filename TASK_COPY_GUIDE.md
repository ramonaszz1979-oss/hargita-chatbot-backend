# Új taskhoz szükséges másolat készítése

Az alábbi módszerek közül választhatsz, attól függően, hogy külön mappába vagy új Git-branchre szeretnél másolatot készíteni.

## 1) Teljes mappa duplikálása új taskhoz (független másolat)
1. Lépj a projekt szülőkönyvtárába (ahol a jelenlegi mappa van):
   ```bash
   cd ..
   ```
2. Másold a mappát új néven (például `hargita-chatbot-backend-uj-task`):
   ```bash
   cp -r hargita-chatbot-backend hargita-chatbot-backend-uj-task
   ```
3. (Opcionális) Ha teljesen új Git-történetet szeretnél, távolítsd el a régi `.git` könyvtárat, majd inicializálj új repót:
   ```bash
   cd hargita-chatbot-backend-uj-task
   rm -rf .git
   git init
   ```
4. Ha szükséges, add hozzá a remote-ot és töltsd fel az új repóba:
   ```bash
   git remote add origin <uj-repo-url>
   git add .
   git commit -m "Kezdő másolat az új taskhoz"
   git push -u origin main
   ```

## 2) Új branch a meglévő repóban (közös történelemmel)
1. Győződj meg róla, hogy a főbranch naprakész:
   ```bash
   git checkout main
   git pull
   ```
2. Hozz létre és válts új branchre az új taskhoz (például `uj-task`):
   ```bash
   git checkout -b uj-task
   ```
3. Dolgozz az új branchen, majd pushold:
   ```bash
   git push -u origin uj-task
   ```

## 3) Csak a WordPress bővítmény ZIP-be csomagolása
Ha kizárólag a pluginra van szükséged:
1. Győződj meg róla, hogy a `wordpress-chatbot-plugin` mappában minden fájl naprakész.
2. Csomagold ZIP-be:
   ```bash
   zip -r egyszeru-chatbot.zip wordpress-chatbot-plugin
   ```
3. Az így kapott ZIP használható új taskban vagy másik WordPress telepítésben.

## Tippek
- A másolat után futtasd a lintert/teszteket az új környezetben is, hogy minden függőség rendben legyen.
- Ha CI/CD vagy deployment kulcsokat használsz, ne másold át automatikusan: az új taskhoz generálj új kulcsokat vagy konfigurációkat.
- Ügyelj arra, hogy ne kerüljön át érzékeny adat (pl. API-kulcs) a másolatba vagy a verziókövetésbe.
