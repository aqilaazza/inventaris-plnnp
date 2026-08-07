import 'package:flutter/foundation.dart';

class ApiConfig {
  /// Base URL secara otomatis menyesuaikan platform:
  /// - Web / Windows Desktop / Local Browser -> http://localhost/inventaris
  /// - Android Emulator -> http://10.0.2.2/inventaris
  /// - HP Android Fisik -> Ganti dengan IP komputer Anda (misal: http://192.168.1.10/inventaris)
  static String get baseUrl {
    if (kIsWeb) {
      return 'http://localhost/inventaris-plnnp';
    }
    // Untuk Android Emulator default 10.0.2.2, namun jika dijalankan di Windows Desktop pake localhost
    if (defaultTargetPlatform == TargetPlatform.windows || 
        defaultTargetPlatform == TargetPlatform.macOS || 
        defaultTargetPlatform == TargetPlatform.linux) {
      return 'http://localhost/inventaris-plnnp';
    }
    return 'http://10.0.2.2/inventaris';
  }
  
  static String get apiUrl => '$baseUrl/api/api_petugas.php';
  
  // URL untuk gambar barang
  static String barangImageUrl(String filename) => '$baseUrl/uploads/barang/$filename';
  
  // URL untuk foto bukti
  static String buktiImageUrl(String filename) => '$baseUrl/uploads/bukti/$filename';
}
