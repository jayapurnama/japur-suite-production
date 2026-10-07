package com.japur.remoteshare;

import android.app.Activity;
import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import android.text.TextUtils;
import android.view.Gravity;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;

public class ShareReceiverActivity extends Activity {
    private TextView resultView;

    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        handleIntent(getIntent());
    }

    @Override protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        handleIntent(intent);
    }

    private void handleIntent(Intent intent) {
        String report = buildReport(intent);
        LinearLayout box = new LinearLayout(this);
        box.setOrientation(LinearLayout.VERTICAL);
        box.setPadding(32, 40, 32, 28);

        TextView title = new TextView(this);
        title.setText("JaPur Remote — Share Diagnostic");
        title.setTextSize(22);
        title.setGravity(Gravity.CENTER);
        box.addView(title, lp());

        TextView info = new TextView(this);
        info.setText("APK ini hanya membaca payload yang diterima dari Android Sharesheet. Tidak mengunduh, mengunggah, atau memproses gambar.");
        info.setPadding(0, 18, 0, 18);
        box.addView(info, lp());

        ScrollView scroll = new ScrollView(this);
        resultView = new TextView(this);
        resultView.setText(report);
        resultView.setTextIsSelectable(true);
        resultView.setTextSize(15);
        scroll.addView(resultView);
        box.addView(scroll, new LinearLayout.LayoutParams(-1, 0, 1));

        Button copy = new Button(this);
        copy.setText("Salin Hasil Diagnostic");
        copy.setOnClickListener(v -> {
            ClipboardManager cm = (ClipboardManager) getSystemService(Context.CLIPBOARD_SERVICE);
            cm.setPrimaryClip(ClipData.newPlainText("JaPur Share Diagnostic", resultView.getText()));
            Toast.makeText(this, "Hasil diagnostic disalin.", Toast.LENGTH_SHORT).show();
        });
        box.addView(copy, lp());

        setContentView(box);
    }

    private LinearLayout.LayoutParams lp() {
        return new LinearLayout.LayoutParams(-1, -2);
    }

    private String buildReport(Intent intent) {
        if (intent == null) return "Tidak ada Intent share yang diterima.";

        StringBuilder s = new StringBuilder();
        s.append("=== JaPur Share Diagnostic v0.1.1 ===\n\n");
        s.append("ACTION\n").append(value(intent.getAction())).append("\n\n");
        s.append("MIME TYPE\n").append(value(intent.getType())).append("\n\n");
        s.append("DATA URI\n").append(value(intent.getData())).append("\n\n");
        s.append("FLAGS\n0x").append(Long.toHexString(intent.getFlags() & 0xffffffffL)).append("\n\n");

        CharSequence text = intent.getCharSequenceExtra(Intent.EXTRA_TEXT);
        CharSequence title = intent.getCharSequenceExtra(Intent.EXTRA_TITLE);
        s.append("EXTRA_TEXT\n").append(text == null ? "(null)" : clip(text.toString())).append("\n\n");
        s.append("EXTRA_TITLE\n").append(title == null ? "(null)" : clip(title.toString())).append("\n\n");

        s.append("EXTRA_STREAM\n");
        Object stream = null;
        try { stream = intent.getExtras() == null ? null : intent.getExtras().get(Intent.EXTRA_STREAM); }
        catch (Exception ignored) {}
        s.append(describe(stream)).append("\n\n");

        ClipData cd = intent.getClipData();
        s.append("CLIPDATA\n");
        if (cd == null) {
            s.append("(null)\n");
        } else {
            s.append("description=").append(cd.getDescription()).append("\n");
            s.append("itemCount=").append(cd.getItemCount()).append("\n");
            for (int i = 0; i < cd.getItemCount(); i++) {
                ClipData.Item item = cd.getItemAt(i);
                s.append("item[").append(i).append("].uri=").append(value(item.getUri())).append("\n");
                s.append("item[").append(i).append("].text=").append(item.getText() == null ? "(null)" : clip(item.getText().toString())).append("\n");
                s.append("item[").append(i).append("].html=").append(item.getHtmlText() == null ? "(null)" : clip(item.getHtmlText())).append("\n");
            }
        }

        Bundle extras = intent.getExtras();
        s.append("\nEXTRA KEYS\n");
        if (extras == null || extras.keySet().isEmpty()) {
            s.append("(none)\n");
        } else {
            for (String key : extras.keySet()) {
                Object obj = null;
                try { obj = extras.get(key); } catch (Exception ignored) {}
                s.append(key).append(" = ").append(describe(obj)).append("\n");
            }
        }

        s.append("\nCOMPONENT\n").append(value(intent.getComponent())).append("\n");
        s.append("\nKESIMPULAN AWAL\n");
        boolean hasStream = stream != null;
        boolean hasUrl = (text != null && text.toString().contains("http")) || intent.getData() != null || (cd != null);
        s.append("Ada EXTRA_STREAM: ").append(hasStream ? "YA" : "TIDAK").append("\n");
        s.append("Ada indikasi URL/share data: ").append(hasUrl ? "YA" : "TIDAK").append("\n");
        return s.toString();
    }

    private String describe(Object obj) {
        if (obj == null) return "(null)";
        if (obj instanceof Uri) return "Uri: " + obj;
        if (obj instanceof CharSequence) return "Text: " + clip(obj.toString());
        if (obj instanceof java.util.ArrayList) return "ArrayList(size=" + ((java.util.ArrayList<?>) obj).size() + "): " + clip(obj.toString());
        return obj.getClass().getName() + ": " + clip(String.valueOf(obj));
    }

    private String value(Object obj) {
        return obj == null ? "(null)" : clip(String.valueOf(obj));
    }

    private String clip(String text) {
        if (text == null) return "(null)";
        text = text.replace("\u0000", "");
        return text.length() > 1200 ? text.substring(0, 1200) + "…[dipotong]" : text;
    }
}
