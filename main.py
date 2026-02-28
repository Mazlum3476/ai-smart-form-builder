import requests
import json
import time

# ---------------------------------------------------------
# OLLAMA AYARLARI (Senin Bilgisayarın)
# ---------------------------------------------------------
URL = "http://localhost:11434/api/generate"
MODEL_NAME = "llama3" 

# ---------------------------------------------------------
# 1. FORM ÜRETME FONKSİYONU
# ---------------------------------------------------------
def generate_form(topic):
    print(f"\n🤖 Yerel Yapay Zeka ({MODEL_NAME}) '{topic}' için çalışıyor... Lütfen bekle...")

   # 🔥 TEMBELLİĞİ YASAKLAYAN AKILLI PROMPT 🔥
    prompt = f"""
    Sen yaratıcı ve zeki bir iş analistisin. Kullanıcı senden '{topic}' için bir HTML form yapısı istiyor.
    
    🔥 ÖNEMLİ STRATEJİK KURAL 🔥: 
    Sadece standart iletişim bilgileriyle (Ad, Soyad, Telefon, Email) YETİNME! Formu dolduran kişiyi analiz edebilmemiz için forma EN AZ 2 TANE MANTIKLI VE SPESİFİK SORU ekle.
    
    ⚠️ DİKKAT (TEMBELLİK YASAK) ⚠️: 
    Sana verdiğim örnekleri ASLA birebir kopyalama! '{topic}' konusunun TAM OLARAK ÖZÜNE İN ve ona en uygun, en detaylı soruyu KENDİN ÜRET.
    - Örneğin '{topic}' bir yemek siparişi ise (Kebap, Pizza vs.): O yemeğe ÖZEL seçenekler sun. (Örn: Kebap için Acı durumu, Soğan tercihi, Dürüm/Porsiyon seçimi gibi). Asla "Alerji" gibi düz ve genelgeçer sorular sorma!
    - Örneğin '{topic}' bir meslek ise: O mesleğin tam olarak hangi yazılım dillerini/araçlarını bildiğini sor.
    Kullanıcının ne için geldiğini anla ve kaliteyi artıracak yaratıcı sorular üret!

    Görevin: Aşağıdaki JSON formatında geçerli bir çıktı üretmek.
    
    KURALLAR:
    1. SADECE JSON kodu ver. Başka hiçbir açıklama, yorum veya yazı yazma.
    2. JSON formatı tam olarak şöyle olmalı:
    {{
      "form_title": "Formun Başlığı",
      "fields": [
        {{ "id": "degisken_adi", "label": "Ekranda Görünen İsim", "type": "text", "required": true }}
      ]
    }}
    3. 'type' alanı sadece şunlar olabilir: text, number, email, select, date, textarea.
    4. Eğer 'type' = 'select' ise, mutlaka o konuya özel mantıklı seçenekleri 'options': ["A", "B", "C"] şeklinde ekle.
    
    Lütfen sadece JSON verisini yaz:
    """
    payload = {
        "model": MODEL_NAME,
        "prompt": prompt,
        "stream": False,
        "format": "json"
    }

    try:
        response = requests.post(URL, json=payload)
        
        if response.status_code == 200:
            result = response.json()
            json_text = result['response']
            
            try:
                data = json.loads(json_text)
                print("✅ BAŞARILI: Stratejik sorular eklendi ve form üretildi!")
                return data
            except json.JSONDecodeError:
                print("❌ Hata: Yapay zeka bozuk JSON üretti.")
                return get_mock_response(topic)
        else:
            print(f"❌ Sunucu Hatası: {response.status_code}")
            return get_mock_response(topic)

    except Exception as e:
        print(f"❌ Bağlantı Hatası: {e}")
        print("💡 İPUCU: Ollama programı açık mı?")
        return get_mock_response(topic)

# ---------------------------------------------------------
# 2. YEDEK (MOCK) FONKSİYON
# ---------------------------------------------------------
def get_mock_response(topic):
    print(f"\n⚠️  Yedek Mod Devrede...")
    return {
        "form_title": f"{topic} (Yedek Mod)",
        "fields": [
            {"id": "ad_soyad", "label": "Ad Soyad", "type": "text", "required": True},
            {"id": "telefon", "label": "Telefon Numarası", "type": "number", "required": True},
            {"id": "ekstra_not", "label": "Ekstra Notunuz", "type": "textarea", "required": False}
        ]
    }

# ---------------------------------------------------------
# 3. ANA PROGRAM
# ---------------------------------------------------------
if __name__ == "__main__":
    while True:
        print("\n" + "="*50)
        print(f"   AI FORM BUILDER (Yerel Mod: {MODEL_NAME})")
        print("="*50)
        user_topic = input("Hangi formu oluşturayım? (Çıkış: 'q'): ")
        
        if user_topic.lower() == 'q':
            break
            
        form_data = generate_form(user_topic)
        
        with open("form_structure.json", "w", encoding="utf-8") as f:
            json.dump(form_data, f, ensure_ascii=False, indent=4)
            
        print(f"💾 'form_structure.json' güncellendi. Web sayfasını (index.html) yenile!")