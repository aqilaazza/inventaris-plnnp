import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../services/auth_service.dart';
import '../services/api_service.dart';
import 'login_screen.dart';

class ProfilScreen extends StatefulWidget {
  const ProfilScreen({super.key});

  @override
  State<ProfilScreen> createState() => _ProfilScreenState();
}

enum _ProfilState { loading, loaded, empty, error }

class _ProfilScreenState extends State<ProfilScreen> {
  String _namaLengkap = '';
  String _username = '';
  String _level = '';
  String? _errorMessage;
  _ProfilState _state = _ProfilState.loading;

  // Palet warna disamakan dengan RiwayatScreen supaya konsisten di seluruh app
  static const _primary = Color(0xFF4F46E5);
  static const _primaryLight = Color(0xFFEEF2FF);
  static const _red = Color(0xFFEF4444);
  static const _redLight = Color(0xFFFEF2F2);
  static const _bg = Color(0xFFF3F4F6);
  static const _textDark = Color(0xFF111827);
  static const _textGrey = Color(0xFF6B7280);
  static const _textMuted = Color(0xFF9CA3AF);
  static const _border = Color(0xFFE5E7EB);
  static const _divider = Color(0xFFF3F4F6);

  @override
  void initState() {
    super.initState();
    _loadProfil();
  }

  bool get _hasData => _namaLengkap.trim().isNotEmpty || _username.trim().isNotEmpty;

  Future<void> _loadProfil({bool isRefresh = false}) async {
    if (!isRefresh) {
      setState(() {
        _state = _ProfilState.loading;
        _errorMessage = null;
      });
    }

    // Tampilkan dulu data cache dari sesi login supaya UI tidak kosong total
    final user = AuthService.currentUser;
    if (user != null) {
      _namaLengkap = user.namaLengkap;
      _username = user.username;
      _level = user.level;
    }

    final result = await ApiService.getProfil();
    if (!mounted) return;

    if (result['success'] == true) {
      final data = result['data'];
      final nama = (data?['nama_lengkap'] ?? _namaLengkap).toString();
      final username = (data?['username'] ?? _username).toString();
      final level = (data?['level'] ?? _level).toString();

      setState(() {
        _namaLengkap = nama;
        _username = username;
        _level = level;
        _errorMessage = null;
        _state = (nama.trim().isEmpty && username.trim().isEmpty)
            ? _ProfilState.empty
            : _ProfilState.loaded;
      });
    } else {
      final message = result['message']?.toString() ?? 'Gagal memuat data profil.';

      if (_hasData) {
        // Sudah ada data sebelumnya (mis. dari refresh) — cukup kasih tahu
        // lewat snackbar, tidak perlu ganti seluruh layar jadi error state.
        setState(() => _state = _ProfilState.loaded);
        if (isRefresh) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(message, style: GoogleFonts.inter(fontSize: 13)),
              backgroundColor: _textDark,
              behavior: SnackBarBehavior.floating,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
          );
        }
      } else {
        setState(() {
          _errorMessage = message;
          _state = _ProfilState.error;
        });
      }
    }
  }

  Future<void> _logout() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => Dialog(
        backgroundColor: Colors.transparent,
        insetPadding: const EdgeInsets.symmetric(horizontal: 32),
        child: Container(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.1),
                blurRadius: 18,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: _primaryLight,
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.logout_rounded, color: _primary, size: 22),
              ),
              const SizedBox(height: 12),
              Text(
                'Keluar dari Aplikasi?',
                textAlign: TextAlign.center,
                style: GoogleFonts.inter(
                  fontWeight: FontWeight.w800,
                  fontSize: 15,
                  color: _textDark,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Anda perlu login kembali nanti.',
                textAlign: TextAlign.center,
                style: GoogleFonts.inter(
                  fontSize: 12,
                  color: _textGrey,
                ),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: SizedBox(
                      height: 40,
                      child: OutlinedButton(
                        onPressed: () => Navigator.pop(ctx, false),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: _textGrey,
                          side: const BorderSide(color: _border),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        child: Text(
                          'Batal',
                          style: GoogleFonts.inter(fontWeight: FontWeight.w700, fontSize: 13),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: SizedBox(
                      height: 40,
                      child: ElevatedButton(
                        onPressed: () => Navigator.pop(ctx, true),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: _primary,
                          foregroundColor: Colors.white,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          elevation: 0,
                        ),
                        child: Text(
                          'Ya, Logout',
                          style: GoogleFonts.inter(fontWeight: FontWeight.w700, fontSize: 13),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );

    if (confirmed == true) {
      await AuthService.logout();
      if (mounted) {
        Navigator.pushAndRemoveUntil(
          context,
          MaterialPageRoute(builder: (_) => const LoginScreen()),
          (route) => false,
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _bg,
      appBar: AppBar(
        title: Text(
          'Inventaris',
          style: GoogleFonts.inter(fontWeight: FontWeight.w800, fontSize: 20),
        ),
        centerTitle: true,
        backgroundColor: Colors.white,
        foregroundColor: _primary,
        elevation: 0,
        scrolledUnderElevation: 1,
      ),
      body: RefreshIndicator(
        onRefresh: () => _loadProfil(isRefresh: true),
        color: _primary,
        backgroundColor: Colors.white,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    switch (_state) {
      case _ProfilState.loading:
        return _buildLoadingState();
      case _ProfilState.error:
        return _buildErrorState();
      case _ProfilState.empty:
        return _buildEmptyState();
      case _ProfilState.loaded:
        return _buildLoadedState();
    }
  }

  // ============================================================
  // LOADING STATE — skeleton placeholder biar tidak "kedip" kosong
  // ============================================================
  Widget _buildLoadingState() {
    Widget bone({double width = double.infinity, double height = 14, double radius = 6}) {
      return Container(
        width: width,
        height: height,
        decoration: BoxDecoration(
          color: const Color(0xFFE5E7EB),
          borderRadius: BorderRadius.circular(radius),
        ),
      );
    }

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      children: [
        bone(width: 120, height: 20),
        const SizedBox(height: 20),
        Container(
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: _border),
          ),
          child: Column(
            children: [
              bone(width: 80, height: 80, radius: 22),
              const SizedBox(height: 16),
              bone(width: 140, height: 16),
              const SizedBox(height: 10),
              bone(width: 70, height: 20, radius: 8),
              const SizedBox(height: 24),
              const Divider(color: _divider),
              const SizedBox(height: 16),
              Row(children: [
                bone(width: 38, height: 38, radius: 11),
                const SizedBox(width: 14),
                Expanded(child: bone(height: 30)),
              ]),
              const SizedBox(height: 14),
              Row(children: [
                bone(width: 38, height: 38, radius: 11),
                const SizedBox(width: 14),
                Expanded(child: bone(height: 30)),
              ]),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Container(
          padding: const EdgeInsets.all(20),
          height: 150,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: _border),
          ),
        ),
      ],
    );
  }

  // ============================================================
  // ERROR STATE
  // ============================================================
  Widget _buildErrorState() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.symmetric(horizontal: 24),
      children: [
        SizedBox(height: MediaQuery.of(context).size.height * 0.15),
        Container(
          width: 72,
          height: 72,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: _redLight,
            shape: BoxShape.circle,
          ),
          child: const Icon(Icons.wifi_off_rounded, color: _red, size: 34),
        ),
        const SizedBox(height: 18),
        Text(
          'Gagal Memuat Profil',
          textAlign: TextAlign.center,
          style: GoogleFonts.inter(fontSize: 16, fontWeight: FontWeight.w800, color: _textDark),
        ),
        const SizedBox(height: 6),
        Text(
          _errorMessage ?? 'Terjadi kesalahan. Coba lagi beberapa saat.',
          textAlign: TextAlign.center,
          style: GoogleFonts.inter(fontSize: 13, color: _textGrey),
        ),
        const SizedBox(height: 20),
        Center(
          child: SizedBox(
            height: 44,
            child: ElevatedButton.icon(
              onPressed: () => _loadProfil(),
              icon: const Icon(Icons.refresh_rounded, size: 18),
              label: Text('Coba Lagi', style: GoogleFonts.inter(fontWeight: FontWeight.w700, fontSize: 13)),
              style: ElevatedButton.styleFrom(
                backgroundColor: _primary,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 20),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                elevation: 0,
              ),
            ),
          ),
        ),
      ],
    );
  }

  // ============================================================
  // EMPTY STATE
  // ============================================================
  Widget _buildEmptyState() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.symmetric(horizontal: 24),
      children: [
        SizedBox(height: MediaQuery.of(context).size.height * 0.15),
        Container(
          width: 72,
          height: 72,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: _primaryLight,
            shape: BoxShape.circle,
          ),
          child: const Icon(Icons.person_off_rounded, color: _primary, size: 32),
        ),
        const SizedBox(height: 18),
        Text(
          'Data Profil Tidak Ditemukan',
          textAlign: TextAlign.center,
          style: GoogleFonts.inter(fontSize: 16, fontWeight: FontWeight.w800, color: _textDark),
        ),
        const SizedBox(height: 6),
        Text(
          'Tarik ke bawah untuk memuat ulang data profil Anda.',
          textAlign: TextAlign.center,
          style: GoogleFonts.inter(fontSize: 13, color: _textGrey),
        ),
        const SizedBox(height: 20),
        Center(
          child: SizedBox(
            height: 44,
            child: OutlinedButton.icon(
              onPressed: () => _loadProfil(),
              icon: const Icon(Icons.refresh_rounded, size: 18),
              label: Text('Muat Ulang', style: GoogleFonts.inter(fontWeight: FontWeight.w700, fontSize: 13)),
              style: OutlinedButton.styleFrom(
                foregroundColor: _primary,
                side: const BorderSide(color: _primary),
                padding: const EdgeInsets.symmetric(horizontal: 20),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
            ),
          ),
        ),
      ],
    );
  }

  // ============================================================
  // LOADED STATE
  // ============================================================
  Widget _buildLoadedState() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          'Profil Saya',
          style: GoogleFonts.inter(
            fontSize: 20,
            fontWeight: FontWeight.w800,
            color: _textDark,
          ),
        ),
        const SizedBox(height: 20),

        // Profile Card
        Container(
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: _border),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.04),
                blurRadius: 16,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Column(
            children: [
              // Avatar
              Container(
                width: 80,
                height: 80,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [Color(0xFF312E81), Color(0xFF4C1D95), Color(0xFF6D28D9)],
                  ),
                  borderRadius: BorderRadius.circular(22),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF4C1D95).withValues(alpha: 0.3),
                      blurRadius: 16,
                      offset: const Offset(0, 6),
                    ),
                  ],
                ),
                child: Center(
                  child: Text(
                    _namaLengkap.isNotEmpty ? _namaLengkap[0].toUpperCase() : 'P',
                    style: GoogleFonts.inter(
                      fontSize: 32,
                      fontWeight: FontWeight.w800,
                      color: Colors.white,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Text(
                _namaLengkap,
                style: GoogleFonts.inter(
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  color: _textDark,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 4),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                decoration: BoxDecoration(
                  color: _primaryLight,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  _level == 'petugas' ? 'Petugas' : _level,
                  style: GoogleFonts.inter(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: _primary,
                  ),
                ),
              ),
              const SizedBox(height: 24),
              const Divider(color: _divider),
              const SizedBox(height: 12),

              // Info items
              _profileInfoItem(Icons.person_rounded, 'Nama Lengkap', _namaLengkap),
              const SizedBox(height: 14),
              _profileInfoItem(Icons.account_circle_rounded, 'Username', _username),
              const SizedBox(height: 14),
              _profileInfoItem(Icons.badge_rounded, 'Level Akun', _level == 'petugas' ? 'Petugas' : _level),
            ],
          ),
        ),

        const SizedBox(height: 16),

        // App Info Card
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: _border),
          ),
          child: Column(
            children: [
              _menuItem(Icons.info_outline_rounded, 'Tentang Aplikasi', 'Inventaris Petugas v1.0.0'),
              const Divider(height: 24, color: _divider),
              _menuItem(Icons.phone_android_rounded, 'Platform', 'Android & Web'),
              const Divider(height: 24, color: _divider),
              _menuItem(Icons.storage_rounded, 'Server', 'MySQL via PHP API'),
            ],
          ),
        ),

        const SizedBox(height: 16),

        // Logout Button
        SizedBox(
          width: double.infinity,
          height: 52,
          child: ElevatedButton.icon(
            onPressed: _logout,
            icon: const Icon(Icons.logout_rounded, size: 20),
            label: Text(
              'Logout',
              style: GoogleFonts.inter(fontWeight: FontWeight.w700, fontSize: 15),
            ),
            style: ElevatedButton.styleFrom(
              backgroundColor: _redLight,
              foregroundColor: _red,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
                side: const BorderSide(color: Color(0xFFFCA5A5)),
              ),
              elevation: 0,
            ),
          ),
        ),

        const SizedBox(height: 24),
        Center(
          child: Text(
            '© 2026 Sistem Inventaris Barang\nAll rights reserved.',
            textAlign: TextAlign.center,
            style: GoogleFonts.inter(fontSize: 11, color: _textMuted),
          ),
        ),
        const SizedBox(height: 8),
      ],
    );
  }

  Widget _profileInfoItem(IconData icon, String label, String value) {
    return Row(
      children: [
        Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: _bg,
            borderRadius: BorderRadius.circular(11),
          ),
          child: Icon(icon, color: _primary, size: 20),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: GoogleFonts.inter(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: _textMuted,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                value,
                style: GoogleFonts.inter(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: _textDark,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _menuItem(IconData icon, String title, String subtitle) {
    return Row(
      children: [
        Icon(icon, color: _textGrey, size: 20),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: GoogleFonts.inter(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: const Color(0xFF374151),
                ),
              ),
              Text(
                subtitle,
                style: GoogleFonts.inter(fontSize: 12, color: _textMuted),
              ),
            ],
          ),
        ),
      ],
    );
  }
}