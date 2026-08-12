import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'dashboard_screen.dart';
import 'scan_screen.dart';
import 'riwayat_screen.dart';
import 'update_gambar_screen.dart';
import 'profil_screen.dart';

class MainScreen extends StatefulWidget {
  const MainScreen({super.key});

  @override
  State<MainScreen> createState() => _MainScreenState();
}

class _MainScreenState extends State<MainScreen> {
  int _currentIndex = 0;

  // Cache layar yang sudah pernah dibuka. Layar yang belum pernah dibuka
  // tidak akan dibangun/di-layout sama sekali (mencegah bug di satu tab
  // membuat seluruh IndexedStack gagal render, termasuk tab Dashboard).
  final List<Widget?> _builtScreens = List<Widget?>.filled(5, null);

  void _selectTab(int index) {
    setState(() => _currentIndex = index);
  }

  Widget _screenFor(int index) {
    if (_builtScreens[index] != null) return _builtScreens[index]!;

    late final Widget screen;
    switch (index) {
      case 0:
        screen = DashboardScreen(
          onNavigateToScan: () => _selectTab(1),
          onNavigateToRiwayat: () => _selectTab(2),
        );
        break;
      case 1:
        screen = const ScanScreen();
        break;
      case 2:
        screen = const RiwayatScreen();
        break;
      case 3:
        screen = const UpdateGambarScreen();
        break;
      case 4:
      default:
        screen = const ProfilScreen();
        break;
    }
    _builtScreens[index] = screen;
    return screen;
  }

  @override
  Widget build(BuildContext context) {
    final List<Widget> screens = List.generate(
      5,
      (i) => i == _currentIndex || _builtScreens[i] != null
          ? _screenFor(i)
          : const SizedBox.shrink(),
    );

    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: screens,
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.06),
              blurRadius: 16,
              offset: const Offset(0, -4),
            ),
          ],
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 6),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _buildNavItem(0, Icons.dashboard_rounded, 'Dashboard'),
                _buildNavItem(1, Icons.qr_code_scanner_rounded, 'Scan'),
                _buildNavItem(2, Icons.history_rounded, 'Riwayat'),
                _buildNavItem(3, Icons.image_rounded, 'Gambar'),
                _buildNavItem(4, Icons.person_rounded, 'Profil'),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildNavItem(int index, IconData icon, String label) {
    final isActive = _currentIndex == index;
    return InkWell(
      onTap: () => _selectTab(index),
      borderRadius: BorderRadius.circular(12),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: EdgeInsets.symmetric(
          horizontal: isActive ? 12 : 8,
          vertical: 6,
        ),
        decoration: BoxDecoration(
          color: isActive ? const Color(0xFF4F46E5).withValues(alpha: 0.1) : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              icon,
              color: isActive ? const Color(0xFF4F46E5) : const Color(0xFF9CA3AF),
              size: 22,
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: GoogleFonts.inter(
                fontSize: 10,
                fontWeight: isActive ? FontWeight.w700 : FontWeight.w500,
                color: isActive ? const Color(0xFF4F46E5) : const Color(0xFF9CA3AF),
              ),
            ),
          ],
        ),
      ),
    );
  }
}