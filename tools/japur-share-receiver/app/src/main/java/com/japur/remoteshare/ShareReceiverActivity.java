package com.japur.remoteshare;

import android.app.Activity;
import android.content.ClipData;
import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import android.view.Gravity;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.util.ArrayList;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class ShareReceiverActivity extends Activity {
    private static final int MAX_BYTES = 15 * 1024 * 1024;
    private TextView status;
    private EditText baseField, keyField;

    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        if (ShareConfig.key(this).isEmpty()) { showSetup(); return; }
        handleIntent(getIntent());
    }

    @Override protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        handleIntent(intent);
    }

    private void showSetup() {
        LinearLayout box = new LinearLayout(this);
        box.setOrientation(LinearLayout.VERTICAL);
        box.setPadding(40,48,40,40);
        box.setGravity(Gravity.CENTER_HORIZONTAL);

        TextView title = new TextView(this);
        title.setText("JaPur Remote Share");
        title.setTextSize(24);
        title.setTextColor(0xff244c3c);
        title.setGravity(Gravity.CENTER);
        box.addView(title, lp());

        TextView info = new TextView(this);
        info.setText("Masukkan alamat JaPur Remote dan Kunci Share Android dari Pengaturan JaPur Remote.");
        info.setPadding(0,24,0,18);
        box.addView(info, lp());

        baseField = new EditText(this);
        baseField.setHint("https://remote.jayapurnama.com");
        baseField.setSingleLine(true);
        baseField.setText(ShareConfig.baseUrl(this));
        box.addView(baseField, lp());

        keyField = new EditText(this);
        keyField.setHint("Kunci Share Android");
        keyField.setSingleLine(true);
        keyField.setInputType(0x00000081);
        box.addView(keyField, lp());

        Button save = new Button(this);
        save.setText("Simpan & Lanjut");
        save.setOnClickListener(v -> {
            if (baseField.getText().toString().trim().isEmpty() ||
                keyField.getText().toString().trim().isEmpty()) {
                Toast.makeText(this,"Alamat dan kunci wajib diisi.",Toast.LENGTH_SHORT).show();
                return;
            }
            ShareConfig.save(this,baseField.getText().toString(),keyField.getText().toString());
            handleIntent(getIntent());
        });
        box.addView(save, lp());
        setContentView(box);
    }

    private LinearLayout.LayoutParams lp(){ return new LinearLayout.LayoutParams(-1,-2); }

    private void handleIntent(Intent intent) {
        if (intent == null) { openRemote("","",""); return; }

        String action=intent.getAction();
        String type=intent.getType();
        CharSequence cs=intent.getCharSequenceExtra(Intent.EXTRA_TEXT);
        String text=cs==null?"":cs.toString();
        CharSequence titleCs=intent.getCharSequenceExtra(Intent.EXTRA_TITLE);
        String title=titleCs==null?"":titleCs.toString();

        Uri image=findSharedImageUri(intent, type);

        if(image!=null) {
            showProgress("Menerima gambar dari aplikasi…");
            final Uri finalImage=image;
            new Thread(() -> {
                String uploaded=upload(finalImage,type);
                runOnUiThread(() -> {
                    if(uploaded!=null&&!uploaded.isEmpty()) {
                        openRemote(uploaded,text,title);
                    } else {
                        openRemote("",text,title);
                        Toast.makeText(this,"Gambar tidak berhasil diunggah; URL/teks share tetap diteruskan.",Toast.LENGTH_LONG).show();
                    }
                });
            }).start();
            return;
        }

        // ChatGPT may share a public share-page URL instead of a file URI.
        // Try to resolve its preview image before falling back to plain URL/text.
        if(isChatGptShareUrl(text)) {
            showProgress("Mengambil gambar dari tautan ChatGPT…");
            final String shareUrl=text.trim();
            new Thread(() -> {
                String uploaded=uploadFromChatGptSharePage(shareUrl);
                runOnUiThread(() -> {
                    if(uploaded!=null&&!uploaded.isEmpty()) {
                        openRemote(uploaded,"",title);
                    } else {
                        openRemote("",text,title);
                        Toast.makeText(this,"Tautan ChatGPT diteruskan karena gambar tidak dapat diambil otomatis.",Toast.LENGTH_LONG).show();
                    }
                });
            }).start();
            return;
        }

        openRemote("","",title);
    }

    private Uri findSharedImageUri(Intent intent, String type) {
        if (type == null || !type.startsWith("image/")) return null;

        if (Intent.ACTION_SEND.equals(intent.getAction())) {
            Uri u = intent.getParcelableExtra(Intent.EXTRA_STREAM);
            if (u != null) return u;
        }

        if (Intent.ACTION_SEND_MULTIPLE.equals(intent.getAction())) {
            ArrayList<Uri> list = intent.getParcelableArrayListExtra(Intent.EXTRA_STREAM);
            if (list != null) {
                for (Uri u : list) if (u != null) return u;
            }
        }

        ClipData clip = intent.getClipData();
        if (clip != null) {
            for (int i=0; i<clip.getItemCount(); i++) {
                Uri u=clip.getItemAt(i).getUri();
                if (u != null) return u;
            }
        }

        Uri data=intent.getData();
        return data;
    }

    private boolean isChatGptShareUrl(String text) {
        try {
            Uri u=Uri.parse(text.trim());
            String host=u.getHost();
            return "chatgpt.com".equalsIgnoreCase(host) &&
                   u.getPath()!=null && u.getPath().startsWith("/s/");
        } catch(Exception e) { return false; }
    }

    private void showProgress(String s){
        status=new TextView(this);
        status.setText(s);
        status.setGravity(Gravity.CENTER);
        status.setPadding(30,60,30,60);
        setContentView(status);
    }

    private String upload(Uri uri,String mime){
        HttpURLConnection c=null;
        try {
            InputStream in=getContentResolver().openInputStream(uri);
            if(in==null) throw new Exception("URI tidak dapat dibaca");
            String name="shared-image.jpg";
            try {
                android.database.Cursor cur=getContentResolver().query(uri,new String[]{"_display_name"},null,null,null);
                if(cur!=null){ if(cur.moveToFirst()) name=cur.getString(0); cur.close(); }
            } catch(Exception ignored){}
            String contentType=mime;
            if(contentType==null||!contentType.startsWith("image/")) contentType=getContentResolver().getType(uri);
            if(contentType==null) contentType="image/jpeg";
            String result=uploadStream(in,name,contentType);
            in.close();
            return result;
        } catch(Exception e) { return null; }
    }

    private String uploadFromChatGptSharePage(String shareUrl) {
        HttpURLConnection page=null;
        try {
            URL u=new URL(shareUrl);
            if(!"https".equalsIgnoreCase(u.getProtocol())) return null;
            page=(HttpURLConnection)u.openConnection();
            page.setInstanceFollowRedirects(true);
            page.setConnectTimeout(15000);
            page.setReadTimeout(20000);
            page.setRequestProperty("User-Agent","JaPur Remote Share/0.1.1");
            int code=page.getResponseCode();
            if(code<200||code>=300) return null;
            String html=readLimited(page.getInputStream(),2*1024*1024);
            String imageUrl=findMetaImage(html);
            if(imageUrl==null||imageUrl.isEmpty()) return null;
            return uploadHttpImage(imageUrl);
        } catch(Exception e) {
            return null;
        } finally {
            if(page!=null) page.disconnect();
        }
    }

    private String findMetaImage(String html) {
        Pattern p=Pattern.compile("<meta[^>]+(?:property|name)=[\\\"'](?:og:image|twitter:image)[\\\"'][^>]+content=[\\\"']([^\\\"']+)[\\\"'][^>]*>",Pattern.CASE_INSENSITIVE);
        Matcher m=p.matcher(html);
        if(m.find()) return htmlDecode(m.group(1));
        Pattern p2=Pattern.compile("<meta[^>]+content=[\\\"']([^\\\"']+)[\\\"'][^>]+(?:property|name)=[\\\"'](?:og:image|twitter:image)[\\\"'][^>]*>",Pattern.CASE_INSENSITIVE);
        m=p2.matcher(html);
        return m.find()?htmlDecode(m.group(1)):null;
    }

    private String htmlDecode(String s) {
        return s.replace("&amp;","&").replace("&quot;","\\\"").replace("&#x2F;","/");
    }

    private String uploadHttpImage(String imageUrl) throws Exception {
        URL u=new URL(imageUrl);
        if(!"https".equalsIgnoreCase(u.getProtocol())) throw new Exception("URL gambar tidak aman");
        HttpURLConnection c=(HttpURLConnection)u.openConnection();
        c.setInstanceFollowRedirects(true);
        c.setConnectTimeout(15000);
        c.setReadTimeout(30000);
        c.setRequestProperty("User-Agent","JaPur Remote Share/0.1.1");
        int code=c.getResponseCode();
        if(code<200||code>=300) throw new Exception("Gambar gagal diambil");
        String ct=c.getContentType();
        if(ct==null||!ct.toLowerCase().startsWith("image/")) throw new Exception("Konten bukan gambar");
        InputStream in=c.getInputStream();
        String name="chatgpt-share-image";
        String ext=ct.toLowerCase().contains("png")?".png":ct.toLowerCase().contains("webp")?".webp":".jpg";
        String result=uploadStream(in,name+ext,ct);
        in.close();
        c.disconnect();
        return result;
    }

    private String uploadStream(InputStream in,String name,String mime) throws Exception {
        HttpURLConnection c=null;
        String boundary="----JaPurShare"+System.currentTimeMillis();
        try {
            URL u=new URL(ShareConfig.baseUrl(this)+"/wp-json/jbr/v1/share-image");
            c=(HttpURLConnection)u.openConnection();
            c.setDoOutput(true); c.setDoInput(true);
            c.setRequestMethod("POST");
            c.setConnectTimeout(20000); c.setReadTimeout(30000);
            c.setRequestProperty("X-JBR-Share-Key",ShareConfig.key(this));
            c.setRequestProperty("Content-Type","multipart/form-data; boundary="+boundary);

            OutputStream out=c.getOutputStream();
            String head="--"+boundary+"\\r\\nContent-Disposition: form-data; name=\\\"jbr_share_image\\\"; filename=\\\""+name.replace("\\"","_")+"\\\"\\r\\nContent-Type:"+mime+"\\r\\n\\r\\n";
            out.write(head.getBytes("UTF-8"));
            byte[] buf=new byte[8192];
            int n,total=0;
            while((n=in.read(buf))!=-1){
                total+=n;
                if(total>MAX_BYTES) throw new Exception("Gambar terlalu besar");
                out.write(buf,0,n);
            }
            out.write(("\\r\\n--"+boundary+"--\\r\\n").getBytes("UTF-8"));
            out.flush(); out.close();

            int code=c.getResponseCode();
            InputStream resp=code>=200&&code<300?c.getInputStream():c.getErrorStream();
            String body=read(resp);
            if(code<200||code>=300) throw new Exception(body);
            JSONObject json=new JSONObject(body);
            return json.optString("url","");
        } finally {
            if(c!=null)c.disconnect();
        }
    }

    private String readLimited(InputStream in,int max) throws Exception {
        ByteArrayOutputStream out=new ByteArrayOutputStream();
        byte[] buf=new byte[8192];
        int n,total=0;
        while((n=in.read(buf))!=-1){
            total+=n;
            if(total>max) break;
            out.write(buf,0,n);
        }
        in.close();
        return out.toString("UTF-8");
    }

    private String read(InputStream in)throws Exception{
        if(in==null)return "";
        BufferedReader r=new BufferedReader(new InputStreamReader(in,"UTF-8"));
        StringBuilder s=new StringBuilder();
        String line;
        while((line=r.readLine())!=null)s.append(line);
        r.close();
        return s.toString();
    }

    private void openRemote(String imageUrl,String text,String title){
        try {
            StringBuilder q=new StringBuilder("?jbr_native_share=1");
            if(!imageUrl.isEmpty())q.append("&url=").append(URLEncoder.encode(imageUrl,"UTF-8"));
            if(!text.isEmpty())q.append("&text=").append(URLEncoder.encode(text,"UTF-8"));
            if(!title.isEmpty())q.append("&title=").append(URLEncoder.encode(title,"UTF-8"));
            Intent i=new Intent(Intent.ACTION_VIEW,Uri.parse(ShareConfig.baseUrl(this)+"/"+q));
            startActivity(i);
            finish();
        } catch(Exception e) {
            Toast.makeText(this,"Gagal membuka JaPur Remote.",Toast.LENGTH_LONG).show();
        }
    }
}