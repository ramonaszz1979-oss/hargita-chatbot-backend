from flask import Flask, request, jsonify
from flask_cors import CORS
import os
import requests
from bs4 import BeautifulSoup
from langchain_google_genai import ChatGoogleGenerativeAI, GoogleGenerativeAIEmbeddings
from langchain.text_splitter import RecursiveCharacterTextSplitter
from langchain.chains import RetrievalQA
from langchain_community.document_loaders import WebBaseLoader
from langchain_community.vectorstores import Chroma
from langchain_core.prompts import ChatPromptTemplate
from langchain_core.output_parsers import StrOutputParser

# --- APP BEÁLLÍTÁSOK ÉS INICIALIZÁLÁS ---
app = Flask(__name__)
CORS(app) 

# Az API kulcsot a Google Cloud Run környezeti változójából veszi át!
GEMINI_API_KEY = os.getenv("GEMINI_API_KEY")

# ChromaDB mappa helye a konténerben
CHROMA_DB_PATH = "./rag_chroma_db"

# RAG elemek inicializálása
# Embeddings (Szavak vektorokká alakítása)
embeddings = GoogleGenerativeAIEmbeddings(model="text-embedding-004", api_key=GEMINI_API_KEY)

# LLM (Gemini 2.5 Flash)
llm = ChatGoogleGenerativeAI(model="gemini-2.5-flash", api_key=GEMINI_API_KEY, temperature=0.2)

# --- Segédfüggvény: Kódtisztítás (A korábbi scraping kód alapján) ---
def clean_html_content(html_content):
    """Kinyeri a tiszta szöveget a HTML-ből."""
    soup = BeautifulSoup(html_content, 'html.parser')
    text_parts = []
    for element in soup.find_all(['p', 'h1', 'h2', 'h3']):
        text = element.get_text(strip=True)
        if text:
            text_parts.append(text)
    full_text = '\n\n'.join(text_parts)
    # Eltávolítja a menüpontokat, hogy csak a tiszta tartalom maradjon
    return full_text.replace("Română", "").replace("English", "").replace("Magyar", "").strip()

# --- 1. VÉGPONT: BETANÍTÁS (TRAINING) ---
@app.route('/train', methods=['POST'])
def train_chatbot():
    """
    [Tanítható funkció a disszertációhoz]
    Fogadja a weboldal URL-jét, letölti, feldolgozza, és betanítja a ChromaDB-t.
    """
    data = request.get_json()
    base_url = data.get('url', 'https://smarted.smartonlineedu.com')
    
    # URL lista automatikus generálása a korábbi lépésekből (index.php/ hozzáadásával)
    pages_to_scrape = [
        f"{base_url}/index.php/termeszeti-gyogytenyezok/mofettak-hargita-megye-titkos-gyogyereje",
        f"{base_url}/index.php/termeszeti-gyogytenyezok/asvanyvizfurdo",
        f"{base_url}/index.php/kezelo-kozpontok/borszek/borszek-fontana-spa",
        f"{base_url}/index.php/patologiak-kezelesi-lehetosegek/kezelesi-lehetosegek",
        # Hozzáadva: Tusnádfürdő
        f"{base_url}/index.php/kezelo-kozpontok/tusnadfurdo/tusnadfurdo-kezelo-kozpont",
    ]
    
    all_documents = []
    
    try:
        for url in pages_to_scrape:
            # WebBaseLoader a tartalom letöltéséhez
            loader = WebBaseLoader(url)
            docs = loader.load()
            
            if docs:
                # Tisztítás a felesleges HTML-től
                docs[0].page_content = clean_html_content(docs[0].page_content)
                all_documents.extend(docs)
                
        if not all_documents:
             return jsonify({"success": False, "message": "Nem sikerült dokumentumot letölteni."}), 400

        # Dokumentumok feldarabolása (chunking) a vektorizáláshoz
        text_splitter = RecursiveCharacterTextSplitter(chunk_size=1000, chunk_overlap=200)
        splits = text_splitter.split_documents(all_documents)

        # ChromaDB létrehozása/frissítése a dokumentumokkal
        # A 'persist' menti az adatbázist a konténerbe (ez a taníthatóság alapja)
        vectorstore = Chroma.from_documents(documents=splits, embedding=embeddings, persist_directory=CHROMA_DB_PATH)
        vectorstore.persist() 
        
        return jsonify({"success": True, "message": f"Sikeres betanítás. {len(splits)} darab dokumentum került a tudásbázisba. Újratanításhoz futtasd újra ezt a végpontot."})

    except Exception as e:
        # A GCR logokba írjuk ki a hiba részleteit
        print(f"HIBA a betanítás során: {e}") 
        return jsonify({"success": False, "message": f"Hiba a betanítás során: {e}"}), 500

# --- 2. VÉGPONT: BESZÉLGETÉS (CHAT) ---
@app.route('/chat', methods=['POST'])
def chat_endpoint():
    """
    Fogadja a felhasználói üzenetet, elvégzi a RAG-et a ChromaDB-vel, 
    és választ generál a Geminivel.
    """
    data = request.get_json()
    user_message = data.get('message', '')
    
    if not user_message:
        return jsonify({"response": "Kérlek, adj meg egy üzenetet."}), 400

    try:
        # ChromaDB betöltése (az előzőleg mentett adatokkal)
        vectorstore = Chroma(persist_directory=CHROMA_DB_PATH, embedding_function=embeddings)
        
        # RAG lánc létrehozása a disszertációhoz szükséges SYSTEM PROMPT-tal
        system_prompt = (
            "Te egy professzionális, Hargita megye gyógyászati és turisztikai kincseire specializálódott asszisztens vagy. "
            "Kizárólag az alábbi kontextusban található tények alapján válaszolj a kérdésre. "
            "Ha a válasz a kontextusban nem található, válaszolj: 'Elnézést, de a tudásbázisban (betanított weboldal tartalmában) nem találtam releváns információt erre a kérdésre.' "
            "Fogalmazz magyarul, használj listákat és emojikat."
            "\n\nKontextus: {context}"
        )
        
        prompt = ChatPromptTemplate.from_messages([
            ("system", system_prompt),
            ("human", "{question}")
        ])
        
        qa_chain = RetrievalQA.from_chain_type(
            llm=llm,
            chain_type="stuff",
            retriever=vectorstore.as_retriever(),
            return_source_documents=False,
            chain_type_kwargs={"prompt": prompt}
        )

        # Válasz generálása
        result = qa_chain.invoke({"query": user_message})
        
        ai_response_text = result["result"]
        
        return jsonify({"response": ai_response_text})
    
    except Exception as e:
        # Hiba a logokban: Keresi a RAG adatbázist, ha nem találja, hibát dob.
        print(f"HIBA a chat végpontban: {e}") 
        return jsonify({"response": "Sajnálom, a tudásbázis nem elérhető. Kérlek, futtasd a betanítást (train)!"}), 500

# --- SZERVER INDÍTÁS ---
if __name__ == '__main__':
    # Helyi teszteléshez
    app.run(debug=True, port=8080)
