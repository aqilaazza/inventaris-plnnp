import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';
import '../models/user.dart';
import '../models/barang.dart';
import '../models/pengecekan.dart';
import 'auth_service.dart';

class ApiService {
  static Map<String, String> get _authHeaders => {
    'Authorization': 'Bearer ${AuthService.token ?? ''}',
  };

  // ============================================================
  // LOGIN
  // ============================================================
  static Future<Map<String, dynamic>> login(String username, String password) async {
    try {
      final response = await http.post(
        Uri.parse('${ApiConfig.apiUrl}?action=login'),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({'username': username, 'password': password}),
      ).timeout(const Duration(seconds: 15));

      final data = json.decode(response.body);
      
      if (data['success'] == true) {
        final user = User.fromJson(data['data']);
        final token = data['data']['token'];
        await AuthService.saveLogin(user, token);
        return {'success': true, 'user': user};
      } else {
        return {'success': false, 'message': data['message'] ?? 'Login gagal'};
      }
    } on SocketException {
      return {'success': false, 'message': 'Tidak dapat terhubung ke server. Periksa koneksi internet.'};
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan: $e'};
    }
  }

  // ============================================================
  // DASHBOARD
  // ============================================================
  static Future<Map<String, dynamic>> getDashboard() async {
    try {
      final response = await http.get(
        Uri.parse('${ApiConfig.apiUrl}?action=dashboard'),
        headers: _authHeaders,
      ).timeout(const Duration(seconds: 15));

      final data = json.decode(response.body);
      
      if (response.statusCode == 401) {
        return {'success': false, 'unauthorized': true, 'message': data['message']};
      }
      
      return data;
    } on SocketException {
      return {'success': false, 'message': 'Tidak dapat terhubung ke server.'};
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan: $e'};
    }
  }

  // ============================================================
  // GET BARANG
  // ============================================================
  static Future<Map<String, dynamic>> getBarang(String code) async {
    try {
      final response = await http.get(
        Uri.parse('${ApiConfig.apiUrl}?action=get_barang&code=$code'),
        headers: _authHeaders,
      ).timeout(const Duration(seconds: 15));

      final data = json.decode(response.body);
      
      if (data['success'] == true) {
        return {'success': true, 'data': Barang.fromJson(data['data'])};
      }
      return {'success': false, 'message': data['message'] ?? 'Barang tidak ditemukan'};
    } on SocketException {
      return {'success': false, 'message': 'Tidak dapat terhubung ke server.'};
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan: $e'};
    }
  }

  // ============================================================
  // LIST BARANG ALL (for Update Gambar dropdown/search)
  // ============================================================
  static Future<Map<String, dynamic>> getListBarangAll({String search = ''}) async {
    try {
      final response = await http.get(
        Uri.parse('${ApiConfig.apiUrl}?action=list_barang_all&search=${Uri.encodeComponent(search)}'),
        headers: _authHeaders,
      ).timeout(const Duration(seconds: 15));

      final data = json.decode(response.body);
      return data;
    } on SocketException {
      return {'success': false, 'message': 'Tidak dapat terhubung ke server.'};
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan: $e'};
    }
  }

  // ============================================================
  // UPDATE GAMBAR BARANG (Upload or Delete)
  // ============================================================
  static Future<Map<String, dynamic>> updateGambarBarang({
    required int idBarang,
    required String subAction, // 'upload' or 'delete'
    File? foto,
  }) async {
    try {
      final request = http.MultipartRequest(
        'POST',
        Uri.parse('${ApiConfig.apiUrl}?action=update_gambar'),
      );
      
      request.headers.addAll(_authHeaders);
      request.fields['id_barang'] = idBarang.toString();
      request.fields['sub_action'] = subAction;
      
      if (subAction == 'upload' && foto != null) {
        request.files.add(await http.MultipartFile.fromPath(
          'foto',
          foto.path,
        ));
      }
      
      final streamedResponse = await request.send().timeout(const Duration(seconds: 30));
      final response = await http.Response.fromStream(streamedResponse);
      final data = json.decode(response.body);
      
      return data;
    } on SocketException {
      return {'success': false, 'message': 'Tidak dapat terhubung ke server.'};
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan: $e'};
    }
  }

  // ============================================================
  // CHECK STATUS PENGECEKAN
  // ============================================================
  static Future<Map<String, dynamic>> checkStatus(int barangId) async {
    try {
      final response = await http.get(
        Uri.parse('${ApiConfig.apiUrl}?action=check_status&barang_id=$barangId'),
        headers: _authHeaders,
      ).timeout(const Duration(seconds: 10));

      return json.decode(response.body);
    } catch (_) {
      return {'already_checked': false};
    }
  }

  // ============================================================
  // SUBMIT PENGECEKAN
  // ============================================================
  static Future<Map<String, dynamic>> submitPengecekan({
    required int idBarang,
    required String kondisiTemuan,
    String catatan = '',
    File? fotoBukti,
  }) async {
    try {
      final request = http.MultipartRequest(
        'POST',
        Uri.parse('${ApiConfig.apiUrl}?action=submit_pengecekan'),
      );
      
      request.headers.addAll(_authHeaders);
      request.fields['id_barang'] = idBarang.toString();
      request.fields['kondisi_temuan'] = kondisiTemuan;
      request.fields['catatan'] = catatan;
      
      if (fotoBukti != null) {
        request.files.add(await http.MultipartFile.fromPath(
          'foto_bukti',
          fotoBukti.path,
        ));
      }
      
      final streamedResponse = await request.send().timeout(const Duration(seconds: 30));
      final response = await http.Response.fromStream(streamedResponse);
      final data = json.decode(response.body);
      
      return data;
    } on SocketException {
      return {'success': false, 'message': 'Tidak dapat terhubung ke server.'};
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan: $e'};
    }
  }

  // ============================================================
  // RIWAYAT PENGECEKAN
  // ============================================================
  static Future<Map<String, dynamic>> getRiwayat({
    int page = 1,
    int limit = 20,
    String search = '',
    String kondisi = '', // '', 'baik', atau 'bermasalah'
  }) async {
    try {
      final params =
          'action=riwayat&page=$page&limit=$limit&search=${Uri.encodeComponent(search)}&kondisi=${Uri.encodeComponent(kondisi)}';
      final response = await http.get(
        Uri.parse('${ApiConfig.apiUrl}?$params'),
        headers: _authHeaders,
      ).timeout(const Duration(seconds: 15));

      final data = json.decode(response.body);
      
      if (data['success'] == true) {
        final items = (data['data'] as List).map((e) => Pengecekan.fromJson(e)).toList();
        return {
          'success': true,
          'data': items,
          'pagination': data['pagination'],
          'summary': data['summary'],
        };
      }
      return {'success': false, 'message': data['message'] ?? 'Gagal memuat riwayat'};
    } on SocketException {
      return {'success': false, 'message': 'Tidak dapat terhubung ke server.'};
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan: $e'};
    }
  }

  // ============================================================
  // PROFIL
  // ============================================================
  static Future<Map<String, dynamic>> getProfil() async {
    try {
      final response = await http.get(
        Uri.parse('${ApiConfig.apiUrl}?action=profil'),
        headers: _authHeaders,
      ).timeout(const Duration(seconds: 10));

      return json.decode(response.body);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan: $e'};
    }
  }
}