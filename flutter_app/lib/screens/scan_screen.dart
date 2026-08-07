import 'dart:io';
import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:image_picker/image_picker.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../services/api_service.dart';
import '../config/api_config.dart';
import '../models/barang.dart';

class _C {
  static const primary = Color(0xFF4F46E5);
  static const primaryDark = Color(0xFF4338CA);
  static const primaryLight = Color(0xFF818CF8);
  static const primarySoft = Color(0xFFEEF2FF);
  static const primarySoftBorder = Color(0xFFDDE3FC);

  static const bg = Color(0xFFF5F5FB);
  static const surface = Colors.white;

  static const ink = Color(0xFF111827);
  static const inkSoft = Color(0xFF4B5563);
  static const inkFaint = Color(0xFF9CA3AF);
  static const line = Color(0xFFECEEF3);

  static const success = Color(0xFF10B981);
  static const warning = Color(0xFFF59E0B);
  static const danger = Color(0xFFEF4444);
}

class ScanScreen extends StatefulWidget {
  const ScanScreen({super.key});

  @override
  State<ScanScreen> createState() => _ScanScreenState();
}

class _SuccessDialogContent extends StatefulWidget {
  final String message;
  final String title;
  const _SuccessDialogContent({
    required this.message,
    this.title = 'Berhasil!',
  });

  @override
  State<_SuccessDialogContent> createState() => _SuccessDialogContentState();
}

class _SuccessDialogContentState extends State<_SuccessDialogContent>
    with TickerProviderStateMixin {
  late final AnimationController _circleController;
  late final AnimationController _checkController;
  late final AnimationController _textController;
  late final AnimationController _progressController;

  late final Animation<double> _circleScale;
  late final Animation<double> _checkScale;
  late final Animation<double> _textFade;
  late final Animation<Offset> _textSlide;

  @override
  void initState() {
    super.initState();

    _circleController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 550),
    );
    _checkController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 400),
    );
    _textController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 400),
    );
    _progressController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2000),
    );

    _circleScale = CurvedAnimation(parent: _circleController, curve: Curves.elasticOut);
    _checkScale = CurvedAnimation(parent: _checkController, curve: Curves.easeOutBack);
    _textFade = CurvedAnimation(parent: _textController, curve: Curves.easeOut);
    _textSlide = Tween<Offset>(begin: const Offset(0, 0.15), end: Offset.zero)
        .animate(CurvedAnimation(parent: _textController, curve: Curves.easeOutCubic));

    _circleController.forward();
    Future.delayed(const Duration(milliseconds: 280), () {
      if (mounted) _checkController.forward();
    });
    Future.delayed(const Duration(milliseconds: 380), () {
      if (mounted) _textController.forward();
    });
    Future.delayed(const Duration(milliseconds: 100), () {
      if (mounted) _progressController.forward();
    });
  }

  @override
  void dispose() {
    _circleController.dispose();
    _checkController.dispose();
    _textController.dispose();
    _progressController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      elevation: 0,
      insetPadding: const EdgeInsets.symmetric(horizontal: 48),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(18),
        child: Container(
          constraints: const BoxConstraints(maxWidth: 300),
          padding: const EdgeInsets.fromLTRB(24, 36, 24, 24),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            boxShadow: [
              BoxShadow(
                color: _C.success.withValues(alpha: 0.18),
                blurRadius: 40,
                offset: const Offset(0, 16),
              ),
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.06),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Circle dengan glow + gradient
              ScaleTransition(
                scale: _circleScale,
                child: Container(
                  width: 88,
                  height: 88,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: const LinearGradient(
                      colors: [Color(0xFF34D399), _C.success],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: _C.success.withValues(alpha: 0.35),
                        blurRadius: 28,
                        spreadRadius: 2,
                        offset: const Offset(0, 10),
                      ),
                    ],
                  ),
                  child: Center(
                    child: ScaleTransition(
                      scale: _checkScale,
                      child: Container(
                        width: 60,
                        height: 60,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: Colors.white.withValues(alpha: 0.18),
                        ),
                        child: const Icon(
                          Icons.check_rounded,
                          color: Colors.white,
                          size: 38,
                        ),
                      ),
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 22),

              FadeTransition(
                opacity: _textFade,
                child: SlideTransition(
                  position: _textSlide,
                  child: Column(
                    children: [
                     Text(
                        widget.title,
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 19,
                          fontWeight: FontWeight.w700,
                          color: _C.ink,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        widget.message,
                        textAlign: TextAlign.center,
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 13.5,
                          height: 1.5,
                          color: _C.inkSoft,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 24),

              // Progress bar tipis penanda auto-close
              ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: SizedBox(
                  height: 4,
                  width: double.infinity,
                  child: AnimatedBuilder(
                    animation: _progressController,
                    builder: (_, __) => LinearProgressIndicator(
                      value: _progressController.value,
                      backgroundColor: _C.line,
                      valueColor: const AlwaysStoppedAnimation(_C.success),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ScanScreenState extends State<ScanScreen> {
  final _codeController = TextEditingController();
  final _catatanController = TextEditingController();
  final _scrollController = ScrollController();

  bool _cameraActive = false;
  MobileScannerController? _scannerController;

  Barang? _selectedBarang;
  String _kondisiTemuan = 'Baik';
  File? _fotoBukti;
  bool _isSearching = false;
  bool _isSubmitting = false;

  bool _alreadyChecked = false;
  String _checkInfo = '';

  bool _barangNotFound = false;
  String _notFoundCode = '';

  String? _periodeInfo;
  bool _hasPeriode = true;

  @override
  void initState() {
    super.initState();
    _loadPeriodeInfo();
  }

  Future<void> _loadPeriodeInfo() async {
    final result = await ApiService.getDashboard();
    if (mounted && result['success'] == true) {
      final data = result['data'];
      final periode = data['periode_aktif'];
      setState(() {
        if (periode != null) {
          _periodeInfo =
              '${periode['nama_periode']} (Batas akhir: ${periode['tgl_selesai']})';
          _hasPeriode = true;
        } else {
          _hasPeriode = false;
        }
      });
    }
  }

  @override
  void dispose() {
    _codeController.dispose();
    _catatanController.dispose();
    _scrollController.dispose();
    _scannerController?.dispose();
    super.dispose();
  }

  void _toggleCamera() {
    setState(() {
      if (_cameraActive) {
        _scannerController?.stop();
        _scannerController?.dispose();
        _scannerController = null;
        _cameraActive = false;
      } else {
        _scannerController = MobileScannerController(
          detectionSpeed: DetectionSpeed.normal,
          facing: CameraFacing.back,
        );
        _cameraActive = true;
      }
    });
  }

  void _onBarcodeDetected(BarcodeCapture capture) {
    final barcodes = capture.barcodes;
    if (barcodes.isNotEmpty) {
      final code = barcodes.first.rawValue;
      if (code != null && code.isNotEmpty) {
        _scannerController?.stop();
        setState(() => _cameraActive = false);
        _codeController.text = code;
        _searchBarang(code);
      }
    }
  }

  Future<void> _searchBarang(String code) async {
      if (code.trim().isEmpty) return;

      setState(() {
        _isSearching = true;
        _selectedBarang = null;
        _alreadyChecked = false;
        _barangNotFound = false;
      });

      final result = await ApiService.getBarang(code.trim());
      if (!mounted) return;

      if (result['success'] == true) {
        final barang = result['data'] as Barang;
        setState(() {
          _selectedBarang = barang;
          _isSearching = false;
          _kondisiTemuan = 'Baik';
          _fotoBukti = null;
          _catatanController.clear();
          _barangNotFound = false;
        });

        Future.delayed(const Duration(milliseconds: 200), () {
          if (_scrollController.hasClients) {
            _scrollController.animateTo(
              _scrollController.position.maxScrollExtent,
              duration: const Duration(milliseconds: 450),
              curve: Curves.easeOutCubic,
            );
          }
        });

        _checkBarangStatus(barang.id);
      } else {
        setState(() {
          _isSearching = false;
          _barangNotFound = true;
          _notFoundCode = code.trim();
        });

        Future.delayed(const Duration(milliseconds: 200), () {
          if (_scrollController.hasClients) {
            _scrollController.animateTo(
              _scrollController.position.maxScrollExtent,
              duration: const Duration(milliseconds: 450),
              curve: Curves.easeOutCubic,
            );
          }
        });
      }
    }

  Future<void> _checkBarangStatus(int barangId) async {
    final result = await ApiService.checkStatus(barangId);
    if (mounted && result['already_checked'] == true) {
      setState(() {
        _alreadyChecked = true;
        _checkInfo =
            'Barang ini sudah pernah dicek oleh ${result['petugas']} pada ${result['tanggal']} dengan temuan ${result['kondisi']}.';
      });
    }
  }

  Future<void> _pickImage() async {
    final picker = ImagePicker();

    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) => ClipRRect(
        borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 12, sigmaY: 12),
          child: Container(
            padding: const EdgeInsets.fromLTRB(20, 14, 20, 28),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.96),
              borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 40,
                  height: 4,
                  margin: const EdgeInsets.only(bottom: 18),
                  decoration: BoxDecoration(
                    color: _C.line,
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
                Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Pilih Sumber Foto',
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                      color: _C.ink,
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                _sheetOption(
                  ctx: ctx,
                  icon: Icons.photo_camera_rounded,
                  title: 'Kamera',
                  subtitle: 'Ambil foto baru',
                  value: ImageSource.camera,
                ),
                const SizedBox(height: 10),
                _sheetOption(
                  ctx: ctx,
                  icon: Icons.image_rounded,
                  title: 'Galeri',
                  subtitle: 'Pilih dari galeri',
                  value: ImageSource.gallery,
                ),
              ],
            ),
          ),
        ),
      ),
    );

    if (source != null) {
      final picked = await picker.pickImage(source: source, maxWidth: 1600, imageQuality: 80);
      if (picked != null && mounted) {
        setState(() => _fotoBukti = File(picked.path));
      }
    }
  }

  Widget _sheetOption({
    required BuildContext ctx,
    required IconData icon,
    required String title,
    required String subtitle,
    required ImageSource value,
  }) {
    return Material(
      color: _C.primarySoft,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () => Navigator.pop(ctx, value),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(icon, color: _C.primary, size: 21),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title,
                        style: GoogleFonts.plusJakartaSans(
                            fontWeight: FontWeight.w700, fontSize: 14, color: _C.ink)),
                    Text(subtitle,
                        style: GoogleFonts.plusJakartaSans(fontSize: 12, color: _C.inkFaint)),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: _C.inkFaint),
            ],
          ),
        ),
      ),
    );
  }

  void _showSnack(String msg, Color color, IconData icon) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            Icon(icon, color: Colors.white, size: 20),
            const SizedBox(width: 10),
            Expanded(
              child: Text(msg, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600)),
            ),
          ],
        ),
        backgroundColor: color,
        behavior: SnackBarBehavior.floating,
        margin: const EdgeInsets.all(14),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        elevation: 0,
      ),
    );
  }

  Future<void> _showSuccessDialog(String message) async {
    showGeneralDialog(
      context: context,
      barrierDismissible: false,
      barrierLabel: 'success',
      barrierColor: Colors.black.withValues(alpha: 0.35),
      transitionDuration: const Duration(milliseconds: 450),
      pageBuilder: (ctx, anim1, anim2) => _SuccessDialogContent(message: message),
      transitionBuilder: (ctx, anim, secondaryAnim, child) {
        return BackdropFilter(
          filter: ImageFilter.blur(
            sigmaX: 6 * anim.value,
            sigmaY: 6 * anim.value,
          ),
          child: FadeTransition(
            opacity: anim,
            child: child,
          ),
        );
      },
    );

    await Future.delayed(const Duration(milliseconds: 2000));
    if (mounted && Navigator.canPop(context)) {
      Navigator.pop(context);
    }
  }

  Future<void> _submitPengecekan() async {
    if (_selectedBarang == null) return;

    if (_kondisiTemuan == 'Rusak' && _fotoBukti == null) {
      _showSnack('Foto bukti wajib dilampirkan untuk kondisi Rusak!', _C.danger, Icons.warning_rounded);
      return;
    }

    setState(() => _isSubmitting = true);

    final result = await ApiService.submitPengecekan(
      idBarang: _selectedBarang!.id,
      kondisiTemuan: _kondisiTemuan,
      catatan: _catatanController.text.trim(),
      fotoBukti: _kondisiTemuan == 'Rusak' ? _fotoBukti : null,
    );

    if (!mounted) return;
    setState(() => _isSubmitting = false);

    if (result['success'] == true) {
      await _showSuccessDialog(result['message'] ?? 'Pengecekan berhasil dikirim.');
      if (!mounted) return;
      setState(() {
        _selectedBarang = null;
        _fotoBukti = null;
        _catatanController.clear();
        _codeController.clear();
        _alreadyChecked = false;
        _kondisiTemuan = 'Baik';
      });
    } else {
      _showSnack(result['message'] ?? 'Gagal mengirim', _C.danger, Icons.error_rounded);
    }
  }

  // ============================================================
  //  BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _C.bg,
      extendBodyBehindAppBar: false,
      appBar: AppBar(
        title: Text(
          'Scan & Cek Barang',
          style: GoogleFonts.plusJakartaSans(
            fontWeight: FontWeight.w800,
            fontSize: 17,
            color: _C.ink,
          ),
        ),
        centerTitle: true,
        backgroundColor: _C.bg,
        foregroundColor: _C.ink,
        elevation: 0,
        scrolledUnderElevation: 0,
        surfaceTintColor: Colors.transparent,
      ),
      body: ListView(
        controller: _scrollController,
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
        children: [
          if (_periodeInfo != null) _buildPeriodeBanner(),
          if (!_hasPeriode) _buildNoPeriodeBanner(),

          const SizedBox(height: 16),

          _buildScannerSection(),

          if (_isSearching) ...[
            const SizedBox(height: 24),
            const Center(
              child: SizedBox(
                width: 26,
                height: 26,
                child: CircularProgressIndicator(strokeWidth: 3, color: _C.primary),
              ),
            ),
          ],

          if (_selectedBarang != null) ...[
            const SizedBox(height: 16),
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 300),
              child: _buildDetailCard(key: ValueKey(_selectedBarang!.id)),
            ),
          ] else if (_barangNotFound) ...[
            const SizedBox(height: 16),
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 300),
              child: _buildNotFoundCard(key: const ValueKey('not_found')),
            ),
          ],
        ],
      ),
    );
  }

  // ------------------------------------------------------------
  //  Periode banners
  // ------------------------------------------------------------

  Widget _buildPeriodeBanner() {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [_C.primary, _C.primaryDark],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: _C.primary.withValues(alpha: 0.28),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.18),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(Icons.event_available_rounded, color: Colors.white, size: 19),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'PERIODE AKTIF',
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    color: Colors.white.withValues(alpha: 0.75),
                    letterSpacing: 0.6,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  _periodeInfo!,
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w700,
                    color: Colors.white,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildNoPeriodeBanner() {
    return Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF7E6),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFFDE4A8)),
      ),
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(Icons.warning_rounded, color: _C.warning, size: 18),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'Tidak ada periode pengecekan aktif saat ini.',
              style: GoogleFonts.plusJakartaSans(
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
                color: const Color(0xFF92400E),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ------------------------------------------------------------
  //  Scanner section
  // ------------------------------------------------------------

  Widget _buildScannerSection() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: _C.line),
        boxShadow: [
          BoxShadow(
            color: _C.ink.withValues(alpha: 0.04),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        children: [
          if (_cameraActive && _scannerController != null) ...[
            _buildScannerViewport(),
            const SizedBox(height: 16),
          ] else ...[
            Container(
              width: 68,
              height: 68,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [_C.primaryLight, _C.primary],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(20),
                boxShadow: [
                  BoxShadow(
                    color: _C.primary.withValues(alpha: 0.25),
                    blurRadius: 16,
                    offset: const Offset(0, 6),
                  ),
                ],
              ),
              child: const Icon(Icons.qr_code_scanner_rounded, color: Colors.white, size: 30),
            ),
            const SizedBox(height: 14),
            Text(
              'Identifikasi Aset',
              style: GoogleFonts.plusJakartaSans(
                fontSize: 17,
                fontWeight: FontWeight.w800,
                color: _C.ink,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              'Gunakan kamera atau masukkan kode manual',
              textAlign: TextAlign.center,
              style: GoogleFonts.plusJakartaSans(fontSize: 12.5, color: _C.inkFaint),
            ),
            const SizedBox(height: 20),
          ],

          _primaryButton(
            onTap: _toggleCamera,
            active: _cameraActive,
            icon: _cameraActive ? Icons.stop_rounded : Icons.camera_alt_rounded,
            label: _cameraActive ? 'Matikan Kamera Scanner' : 'Mulai Kamera Scanner',
          ),

          const SizedBox(height: 20),

          Row(
            children: [
              const Expanded(child: Divider(color: _C.line, thickness: 1)),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: Text(
                  'ATAU',
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w700,
                    color: _C.inkFaint,
                    letterSpacing: 0.8,
                  ),
                ),
              ),
              const Expanded(child: Divider(color: _C.line, thickness: 1)),
            ],
          ),

          const SizedBox(height: 16),

          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: TextField(
                  controller: _codeController,
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.4,
                    color: _C.ink,
                  ),
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    prefixIcon: const Icon(Icons.tag_rounded, size: 19, color: _C.inkFaint),
                    hintText: 'Contoh: 00005 atau 5',
                    hintStyle: GoogleFonts.plusJakartaSans(
                        color: _C.inkFaint, fontWeight: FontWeight.w400, fontSize: 14),
                    filled: true,
                    fillColor: _C.bg,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14),
                      borderSide: BorderSide.none,
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14),
                      borderSide: BorderSide.none,
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14),
                      borderSide: const BorderSide(color: _C.primary, width: 1.6),
                    ),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 4, vertical: 14),
                  ),
                  onSubmitted: (v) => _searchBarang(v),
                ),
              ),
              const SizedBox(width: 10),
              SizedBox(
                height: 52,
                width: 52,
                child: ElevatedButton(
                  onPressed: _isSearching ? null : () => _searchBarang(_codeController.text),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _C.ink,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    elevation: 0,
                    padding: EdgeInsets.zero,
                  ),
                  child: const Icon(Icons.search_rounded, size: 22),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildScannerViewport() {
    return ClipRRect(
      borderRadius: BorderRadius.circular(18),
      child: SizedBox(
        height: 260,
        child: Stack(
          fit: StackFit.expand,
          children: [
            MobileScanner(
              controller: _scannerController!,
              onDetect: _onBarcodeDetected,
            ),
            IgnorePointer(
              child: Center(
                child: Container(
                  width: 200,
                  height: 130,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: Colors.white.withValues(alpha: 0.85), width: 2),
                  ),
                ),
              ),
            ),
            Positioned(
              top: 10,
              left: 10,
              right: 10,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: Colors.black.withValues(alpha: 0.45),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 8,
                      height: 8,
                      decoration: const BoxDecoration(color: _C.danger, shape: BoxShape.circle),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      'Mencari barcode…',
                      style: GoogleFonts.plusJakartaSans(
                        color: Colors.white,
                        fontSize: 11.5,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Tombol utama gradient (indigo/ungu), dipakai untuk mulai kamera & kirim laporan.
  Widget _primaryButton({
    required VoidCallback? onTap,
    required IconData icon,
    required String label,
    bool active = false,
    bool loading = false,
  }) {
    final colors = active
        ? const [Color(0xFFFB7185), _C.danger]
        : const [_C.primaryLight, _C.primary];
    final shadowColor = active ? _C.danger : _C.primary;

    return AnimatedOpacity(
      duration: const Duration(milliseconds: 200),
      opacity: onTap == null ? 0.55 : 1,
      child: Container(
        width: double.infinity,
        decoration: BoxDecoration(
          gradient: LinearGradient(colors: colors, begin: Alignment.centerLeft, end: Alignment.centerRight),
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(color: shadowColor.withValues(alpha: 0.3), blurRadius: 18, offset: const Offset(0, 8)),
          ],
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 16),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  if (loading)
                    const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                    )
                  else
                    Icon(icon, color: Colors.white, size: 20),
                  const SizedBox(width: 10),
                  Text(
                    label,
                    style: GoogleFonts.plusJakartaSans(
                        color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  // ------------------------------------------------------------
  //  Detail card
  // ------------------------------------------------------------

  Widget _buildDetailCard({Key? key}) {
    final barang = _selectedBarang!;
    return Container(
      key: key,
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: _C.line),
        boxShadow: [
          BoxShadow(
            color: _C.ink.withValues(alpha: 0.05),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        children: [
          // Header gradient ungu (dulu abu gelap)
          Container(
            padding: const EdgeInsets.all(18),
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [_C.primaryDark, _C.primary],
                begin: Alignment.centerLeft,
                end: Alignment.centerRight,
              ),
              borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
            ),
            child: Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.16),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: const Icon(Icons.inventory_2_rounded, color: Colors.white, size: 20),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        barang.namaBarang,
                        style: GoogleFonts.plusJakartaSans(
                            fontSize: 15.5, fontWeight: FontWeight.w800, color: Colors.white),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          Icon(Icons.qr_code_2_rounded, color: Colors.white.withValues(alpha: 0.65), size: 14),
                          const SizedBox(width: 4),
                          Text(
                            '# ${barang.formattedCode}',
                            style: GoogleFonts.plusJakartaSans(
                              fontSize: 12,
                              color: Colors.white.withValues(alpha: 0.75),
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: _C.bg,
                    borderRadius: BorderRadius.circular(18),
                  ),
                  child: Column(
                    children: [
                      if (barang.foto != null && barang.foto!.isNotEmpty)
                        Container(
                          margin: const EdgeInsets.only(bottom: 12),
                          height: 130,
                          width: double.infinity,
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(14),
                            child: CachedNetworkImage(
                              imageUrl: ApiConfig.barangImageUrl(barang.foto!),
                              fit: BoxFit.contain,
                              placeholder: (_, __) => Container(
                                color: Colors.white,
                                child: const Center(
                                  child: SizedBox(
                                    width: 22,
                                    height: 22,
                                    child: CircularProgressIndicator(strokeWidth: 2, color: _C.primary),
                                  ),
                                ),
                              ),
                              errorWidget: (_, __, ___) => Container(
                                color: Colors.white,
                                child: const Icon(Icons.image_not_supported_outlined,
                                    size: 36, color: _C.inkFaint),
                              ),
                            ),
                          ),
                        ),
                      Row(
                        children: [
                          Expanded(child: _infoItem('KATEGORI', barang.namaKategori)),
                          const SizedBox(width: 12),
                          Expanded(child: _infoItem('MERK', barang.namaMerk)),
                        ],
                      ),
                      const SizedBox(height: 14),
                      Row(
                        children: [
                          Expanded(
                            child: _infoItemBadge('KONDISI TERAKHIR', barang.kondisi, _kondisiColor(barang.kondisi)),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: _infoItemBadge(
                              'STATUS AKTIF',
                              barang.statusAktif == 'aktif' ? 'Aktif' : 'Nonaktif',
                              barang.statusAktif == 'aktif' ? _C.success : const Color(0xFF6B7280),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),
                      _infoItemWithIcon(
                        'LOKASI PENEMPATAN',
                        '${barang.namaUnit} → ${barang.namaRuang}',
                        Icons.location_on_rounded,
                        _C.primary,
                      ),
                    ],
                  ),
                ),

                if (_alreadyChecked) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(13),
                    decoration: BoxDecoration(
                      color: _C.primarySoft,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: _C.primarySoftBorder),
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(Icons.history_rounded, color: _C.primary, size: 18),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            _checkInfo,
                            style: GoogleFonts.plusJakartaSans(fontSize: 12, color: _C.inkSoft),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],

                const SizedBox(height: 22),

                Text(
                  'Kondisi Fisik Saat Ini *',
                  style: GoogleFonts.plusJakartaSans(
                      fontSize: 13, fontWeight: FontWeight.w700, color: _C.ink),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    _buildKondisiButton('Baik', Icons.check_circle_rounded, _C.success),
                    const SizedBox(width: 10),
                    _buildKondisiButton('Rusak', Icons.build_rounded, _C.warning),
                    const SizedBox(width: 10),
                    _buildKondisiButton('Hilang', Icons.cancel_rounded, _C.danger),
                  ],
                ),

                AnimatedSize(
                  duration: const Duration(milliseconds: 220),
                  curve: Curves.easeOut,
                  child: _kondisiTemuan == 'Rusak'
                      ? Padding(
                          padding: const EdgeInsets.only(top: 16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Lampirkan Foto Bukti *',
                                style: GoogleFonts.plusJakartaSans(
                                    fontSize: 13, fontWeight: FontWeight.w700, color: _C.danger),
                              ),
                              const SizedBox(height: 8),
                              if (_fotoBukti != null)
                                Stack(
                                  children: [
                                    ClipRRect(
                                      borderRadius: BorderRadius.circular(14),
                                      child: Image.file(
                                        _fotoBukti!,
                                        height: 130,
                                        width: double.infinity,
                                        fit: BoxFit.cover,
                                      ),
                                    ),
                                    Positioned(
                                      top: 8,
                                      right: 8,
                                      child: GestureDetector(
                                        onTap: () => setState(() => _fotoBukti = null),
                                        child: Container(
                                          padding: const EdgeInsets.all(5),
                                          decoration: BoxDecoration(
                                            color: Colors.black.withValues(alpha: 0.55),
                                            shape: BoxShape.circle,
                                          ),
                                          child: const Icon(Icons.close_rounded, color: Colors.white, size: 16),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              if (_fotoBukti != null) const SizedBox(height: 8),
                              OutlinedButton.icon(
                                onPressed: _pickImage,
                                icon: const Icon(Icons.camera_alt_rounded, size: 18),
                                label: Text(
                                  _fotoBukti != null ? 'Ganti Foto' : 'Ambil Foto / Pilih File',
                                  style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600, fontSize: 13),
                                ),
                                style: OutlinedButton.styleFrom(
                                  foregroundColor: _C.primary,
                                  side: const BorderSide(color: _C.primary),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                  padding: const EdgeInsets.symmetric(vertical: 13),
                                  minimumSize: const Size(double.infinity, 0),
                                ),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                'Melampirkan foto bukti kondisi fisik wajib untuk temuan barang rusak.',
                                style: GoogleFonts.plusJakartaSans(
                                    fontSize: 11, color: _C.danger, fontWeight: FontWeight.w600),
                              ),
                            ],
                          ),
                        )
                      : const SizedBox.shrink(),
                ),

                const SizedBox(height: 16),

                Text(
                  'Catatan Tambahan',
                  style: GoogleFonts.plusJakartaSans(
                      fontSize: 13, fontWeight: FontWeight.w700, color: _C.ink),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _catatanController,
                  maxLines: 3,
                  style: GoogleFonts.plusJakartaSans(fontSize: 13, color: _C.ink),
                  decoration: InputDecoration(
                    hintText: 'Tuliskan keterangan detail kondisi fisik barang jika diperlukan...',
                    hintStyle: GoogleFonts.plusJakartaSans(color: _C.inkFaint, fontSize: 13),
                    filled: true,
                    fillColor: _C.bg,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14),
                      borderSide: BorderSide.none,
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14),
                      borderSide: BorderSide.none,
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14),
                      borderSide: const BorderSide(color: _C.primary, width: 1.6),
                    ),
                    contentPadding: const EdgeInsets.all(14),
                  ),
                ),

                const SizedBox(height: 22),

                _primaryButton(
                  onTap: _isSubmitting ? null : _submitPengecekan,
                  icon: Icons.send_rounded,
                  label: _isSubmitting ? 'Memproses...' : 'Kirim Laporan',
                  loading: _isSubmitting,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ------------------------------------------------------------
  //  Kondisi barang tidak ditemukan
  // --
  Widget _buildNotFoundCard({Key? key}) {
    return Container(
      key: key,
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: _C.line),
        boxShadow: [
          BoxShadow(
            color: _C.ink.withValues(alpha: 0.04),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        children: [
          Container(
            width: 64,
            height: 64,
            decoration: BoxDecoration(
              color: _C.danger.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(18),
            ),
            child: const Icon(Icons.search_off_rounded, color: _C.danger, size: 30),
          ),
          const SizedBox(height: 16),
          Text(
            'Barang Tidak Ditemukan',
            style: GoogleFonts.plusJakartaSans(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: _C.ink,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            _notFoundCode.isNotEmpty
                ? 'Tidak ada data barang dengan kode "$_notFoundCode".'
                : 'Tidak ada data barang dengan kode tersebut.',
            textAlign: TextAlign.center,
            style: GoogleFonts.plusJakartaSans(fontSize: 13, color: _C.inkFaint),
          ),
          const SizedBox(height: 4),
          Text(
            'Periksa kembali kode barang atau coba scan ulang.',
            textAlign: TextAlign.center,
            style: GoogleFonts.plusJakartaSans(fontSize: 12, color: _C.inkFaint),
          ),
          const SizedBox(height: 18),
          OutlinedButton.icon(
            onPressed: () {
              setState(() {
                _barangNotFound = false;
                _codeController.clear();
              });
            },
            icon: const Icon(Icons.refresh_rounded, size: 18),
            label: Text(
              'Coba Lagi',
              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600, fontSize: 13),
            ),
            style: OutlinedButton.styleFrom(
              foregroundColor: _C.primary,
              side: const BorderSide(color: _C.primary),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
            ),
          ),
        ],
      ),
    );
  }

  // ------------------------------------------------------------
  //  Kondisi fisik card button
  // ------------------------------------------------------------

  Widget _buildKondisiButton(String label, IconData icon, Color color) {
    final isSelected = _kondisiTemuan == label;
    return Expanded(
      child: GestureDetector(
        onTap: () {
          setState(() {
            _kondisiTemuan = label;
            if (label != 'Rusak') _fotoBukti = null;
          });
        },
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
          padding: const EdgeInsets.symmetric(vertical: 14),
          decoration: BoxDecoration(
            color: isSelected ? color.withValues(alpha: 0.10) : _C.bg,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: isSelected ? color : Colors.transparent,
              width: 1.6,
            ),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 21, color: isSelected ? color : _C.inkFaint),
              const SizedBox(height: 6),
              Text(
                label,
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w700,
                  color: isSelected ? color : _C.inkSoft,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Color _kondisiColor(String kondisi) {
    switch (kondisi) {
      case 'Baik':
        return _C.success;
      case 'Rusak':
        return _C.warning;
      case 'Hilang':
        return _C.danger;
      default:
        return const Color(0xFF6B7280);
    }
  }

  // ------------------------------------------------------------
  //  Info helpers
  // ------------------------------------------------------------

  Widget _infoItem(String label, String value) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: GoogleFonts.plusJakartaSans(
            fontSize: 10,
            fontWeight: FontWeight.w700,
            color: _C.inkFaint,
            letterSpacing: 0.5,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          value,
          style: GoogleFonts.plusJakartaSans(fontSize: 13, fontWeight: FontWeight.w700, color: _C.ink),
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),
      ],
    );
  }

  Widget _infoItemBadge(String label, String value, Color color) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: GoogleFonts.plusJakartaSans(
            fontSize: 10,
            fontWeight: FontWeight.w700,
            color: _C.inkFaint,
            letterSpacing: 0.5,
          ),
        ),
        const SizedBox(height: 5),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(20),
          ),
          child: Text(
            value,
            style: GoogleFonts.plusJakartaSans(fontSize: 12, fontWeight: FontWeight.w700, color: color),
          ),
        ),
      ],
    );
  }

  Widget _infoItemWithIcon(String label, String value, IconData icon, Color iconColor) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: GoogleFonts.plusJakartaSans(
            fontSize: 10,
            fontWeight: FontWeight.w700,
            color: _C.inkFaint,
            letterSpacing: 0.5,
          ),
        ),
        const SizedBox(height: 3),
        Row(
          children: [
            Icon(icon, size: 14, color: iconColor),
            const SizedBox(width: 4),
            Expanded(
              child: Text(
                value,
                style: GoogleFonts.plusJakartaSans(fontSize: 13, fontWeight: FontWeight.w700, color: _C.ink),
              ),
            ),
          ],
        ),
      ],
    );
  }
}