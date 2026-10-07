package com.japur.remoteshare;

import android.app.Activity;
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
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.util.ArrayList;

public class ShareReceiverActivity extends Activity {
    private TextView status;
    private EditText baseField, keyField;

    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        if (ShareConfig.key(this).isEmpty()) { showSetup(); return; }
        handleIntent(getIntent());
    }

    @Override protected void onNewIntent(Intent intent) { super.onNewIntent(intent); setIntent(intent); handleIntent(intent); }

    private void showSetup() {
        LinearLayout box = new LinearLayout(this); box.setOrientation(LinearLayout.VERTICAL); box.setPadding(40,48,40,40); box.setGravity(Gravity.CENTER_HORIZONTAL);
        TextView title = new TextView(this); title.setText("JaPur Remote Share"); title.setTextSize(24); title.setTextColor(0xff244c3c); title.setGravity(Gravity.CENTER); box.addView(title, lp());
        TextView info = new TextView(this); info.setText("Masukkan alamat JaPur Remote dan Kunci Share Android dari Pengaturan JaPur Remote."); info.setPadding(0,24,0,18); box.addView(info, lp());
        baseField = new EditText(this); baseField.setHint("https://remote.jayapurnama.com"); baseField.setSingleLine(true); baseField.setText(ShareConfig.baseUrl(this)); box.addView(baseField, lp());
        keyField = new EditText(this); keyField.setHint("Kunci Share Android"); keyField.setSingleLine(true); keyField.setInputType(0x00000081); box.addView(keyField, lp());
        Button save = new Button(this); save.setText("Simpan & Lanjut"); save.setOnClickListener(v -> { if(baseField.getText().toString().trim().isEmpty()||keyField.getText().toString().trim().isEmpty()){Toast.makeText(this,"Alamat dan kunci wajib diisi.",Toast.LENGTH_SHORT).show();return;} ShareConfig.save(this,baseField.getText().toString(),keyField.getText().toString()); handleIntent(getIntent()); }); box.addView(save, lp());
        setContentView(box);
    }
    private LinearLayout.LayoutParams lp(){return new LinearLayout.LayoutParams(-1,-2);}

    private void handleIntent(Intent intent) {
        if (intent == null) { openRemote("","",""); return; }
        String action=intent.getAction(); String type=intent.getType();
        CharSequence cs=intent.getCharSequenceExtra(Intent.EXTRA_TEXT); String text=cs==null?"":cs.toString();
        CharSequence titleCs=intent.getCharSequenceExtra(Intent.EXTRA_TITLE); String title=titleCs==null?"":titleCs.toString();
        Uri image=null;
        if(Intent.ACTION_SEND.equals(action) && type!=null && type.startsWith("image/")) image=intent.getParcelableExtra(Intent.EXTRA_STREAM);
        if(Intent.ACTION_SEND_MULTIPLE.equals(action) && type!=null && type.startsWith("image/")) { ArrayList<Uri> list=intent.getParcelableArrayListExtra(Intent.EXTRA_STREAM); if(list!=null&&!list.isEmpty()) image=list.get(0); }
        if(image!=null){
            showProgress("Menerima gambar dari ChatGPT…");
            final Uri finalImage=image; new Thread(() -> { String uploaded=upload(finalImage,type); runOnUiThread(() -> { if(uploaded!=null&&!uploaded.isEmpty()){ openRemote(uploaded,text,title); } else { openRemote("",text,title); Toast.makeText(this,"Gambar tidak berhasil diunggah; URL/teks share tetap diteruskan.",Toast.LENGTH_LONG).show(); } }); }).start();
        } else { openRemote("",text,title); }
    }
    private void showProgress(String s){ status=new TextView(this); status.setText(s); status.setGravity(Gravity.CENTER); status.setPadding(30,60,30,60); setContentView(status); }

    private String upload(Uri uri,String mime){
        HttpURLConnection c=null; String boundary="----JaPurShare"+System.currentTimeMillis();
        try{
            URL u=new URL(ShareConfig.baseUrl(this)+"/wp-json/jbr/v1/share-image"); c=(HttpURLConnection)u.openConnection(); c.setDoOutput(true); c.setDoInput(true); c.setRequestMethod("POST"); c.setConnectTimeout(20000); c.setReadTimeout(30000); c.setRequestProperty("X-JBR-Share-Key",ShareConfig.key(this)); c.setRequestProperty("Content-Type","multipart/form-data; boundary="+boundary);
            String name="shared-image.jpg"; try{android.database.Cursor cur=getContentResolver().query(uri,new String[]{"_display_name"},null,null,null); if(cur!=null){if(cur.moveToFirst()) name=cur.getString(0);cur.close();}}catch(Exception ignored){}
            OutputStream out=c.getOutputStream(); String head="--"+boundary+"\r\nContent-Disposition: form-data; name=\"jbr_share_image\"; filename=\""+name.replace("\"","_")+"\"\r\nContent-Type:"+(mime==null?"image/jpeg":mime)+"\r\n\r\n"; out.write(head.getBytes("UTF-8"));
            InputStream in=getContentResolver().openInputStream(uri); if(in==null) throw new Exception("URI tidak dapat dibaca"); byte[] buf=new byte[8192]; int n,total=0; while((n=in.read(buf))!=-1){total+=n;if(total>15*1024*1024)throw new Exception("Gambar terlalu besar");out.write(buf,0,n);} in.close(); out.write(("\r\n--"+boundary+"--\r\n").getBytes("UTF-8")); out.flush(); out.close();
            int code=c.getResponseCode(); InputStream resp=code>=200&&code<300?c.getInputStream():c.getErrorStream(); String body=read(resp); if(code<200||code>=300) throw new Exception(body);
            JSONObject json=new JSONObject(body); return json.optString("url","");
        }catch(Exception e){return null;}finally{if(c!=null)c.disconnect();}
    }
    private String read(InputStream in)throws Exception{if(in==null)return "";BufferedReader r=new BufferedReader(new InputStreamReader(in,"UTF-8"));StringBuilder s=new StringBuilder();String line;while((line=r.readLine())!=null)s.append(line);r.close();return s.toString();}

    private void openRemote(String imageUrl,String text,String title){
        try{
            StringBuilder q=new StringBuilder("?jbr_native_share=1"); if(!imageUrl.isEmpty())q.append("&url=").append(URLEncoder.encode(imageUrl,"UTF-8")); if(!text.isEmpty())q.append("&text=").append(URLEncoder.encode(text,"UTF-8")); if(!title.isEmpty())q.append("&title=").append(URLEncoder.encode(title,"UTF-8"));
            Intent i=new Intent(Intent.ACTION_VIEW,Uri.parse(ShareConfig.baseUrl(this)+"/"+q)); startActivity(i); finish();
        }catch(Exception e){Toast.makeText(this,"Gagal membuka JaPur Remote.",Toast.LENGTH_LONG).show();}
    }
}