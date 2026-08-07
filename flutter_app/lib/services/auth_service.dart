import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user.dart';

class AuthService {
  static const String _tokenKey = 'auth_token';
  static const String _userKey = 'auth_user';
  
  static User? _currentUser;
  static String? _token;

  static User? get currentUser => _currentUser;
  static String? get token => _token;
  static bool get isLoggedIn => _token != null && _currentUser != null;

  /// Initialize auth state from stored preferences
  static Future<bool> init() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString(_tokenKey);
    final userJson = prefs.getString(_userKey);
    
    if (_token != null && userJson != null) {
      try {
        _currentUser = User.fromJson(json.decode(userJson));
        return true;
      } catch (_) {
        await logout();
        return false;
      }
    }
    return false;
  }

  /// Save login data
  static Future<void> saveLogin(User user, String token) async {
    final prefs = await SharedPreferences.getInstance();
    _currentUser = user;
    _token = token;
    await prefs.setString(_tokenKey, token);
    await prefs.setString(_userKey, json.encode(user.toJson()));
  }

  /// Clear login data
  static Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    _currentUser = null;
    _token = null;
    await prefs.remove(_tokenKey);
    await prefs.remove(_userKey);
  }
}
