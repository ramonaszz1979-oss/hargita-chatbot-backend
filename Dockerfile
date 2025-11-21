# Alap kép (Python)
FROM python:3.10-slim

# Beállítja a munkakönyvtárat
WORKDIR /app

# Másolja a requirements fájlt és telepíti a függőségeket
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Másolja a teljes kódot a konténerbe
COPY . .

# Beállítja az indítási parancsot a Gunicorn szerverrel
# $PORT a GCR által automatikusan megadott portot veszi át
CMD exec gunicorn --bind :$PORT --workers 1 --threads 8 app:app
