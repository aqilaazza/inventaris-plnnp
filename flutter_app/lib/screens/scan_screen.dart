import 'dart:io';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:image_picker/image_picker.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../services/api_service.dart';
import '../config/api_config.dart';
import '../models/barang.dart';

class ScanScreen extends StatefulWidget {
  const ScanScreen({super.key});

  @override
  State<ScanScreen> createState() => _ScanScreenState();
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

  // Check status
  bool _alreadyChecked = false;
  String _checkInfo = '';

  // Periode aktif info
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
          _periodeInfo = '${periode['nama_periode']} (Batas akhir: ${periode['tgl_selesai']})';
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
        // Stop camera and search
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
      });

      // Scroll to details
      Future.delayed(const Duration(milliseconds: 200), () {
        if (_scrollController.hasClients) {
          _scrollController.animateTo(
            _scrollController.position.maxScrollExtent,
            duration: const Duration(milliseconds: 400),
            curve: Curves.easeOutCubic,
          );
        }
      });

      // Check status
      _checkBarangStatus(barang.id);
    } else {
      setState(() => _isSearching = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Row(
              children: [
                const Icon(Icons.warning_amber_rounded, color: Colors.white, size: 20),
                const SizedBox(width: 8),
                Expanded(child: Text(result['message'] ?? 'Barang tidak ditemukan')),
              ],
            ),
            backgroundColor: const Color(0xFFF59E0B),
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        );
      }
    }
  }

  Future<void> _checkBarangStatus(int barangId) async {
    final result = await ApiService.checkStatus(barangId);
    if (mounted && result['already_checked'] == true) {
      setState(() {
        _alreadyChecked = true;
        _checkInfo = 'Barang ini sudah pernah dicek oleh ${result['petugas']} pada ${result['tanggal']} dengan temuan ${result['kondisi']}.';
      });
    }
  }

  Future<void> _pickImage() async {
    final picker = ImagePicker();

    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Pilih Sumber Foto',
              style: GoogleFonts.inter(fontSize: 16, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 16),
            ListTile(
              leading: Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: const Color(0xFFEEF2FF),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.camera_alt_rounded, color: Color(0xFF4F46E5)),
              ),
              title: Text('Kamera', style: GoogleFonts.inter(fontWeight: FontWeight.w600)),
              subtitle: Text('Ambil foto baru', style: GoogleFonts.inter(fontSize: 12)),
              onTap: () => Navigator.pop(ctx, ImageSource.camera),
            ),
            ListTile(
              leading: Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: const Color(0xFFEEF2FF),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.photo_library_rounded, color: Color(0xFF4F46E5)),
              ),
              title: Text('Galeri', style: GoogleFonts.inter(fontWeight: FontWeight.w600)),
              subtitle: Text('Pilih dari galeri', style: GoogleFonts.inter(fontSize: 12)),
              onTap: () => Navigator.pop(ctx, ImageSource.gallery),
            ),
          ],
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

  Future<void> _submitPengecekan() async {
    if (_selectedBarang == null) return;

    // Foto bukti hanya wajib untuk kondisi "Rusak".
    if (_kondisiTemuan == 'Rusak' && _fotoBukti == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('Foto bukti wajib dilampirkan untuk kondisi Rusak!'),
          backgroundColor: const Color(0xFFEF4444),
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      );
      return;
    }

    setState(() => _isSubmitting = true);

    final result = await ApiService.submitPengecekan(
      idBarang: _selectedBarang!.id,
      kondisiTemuan: _kondisiTemuan,
      catatan: _catatanController.text.trim(),
      // Foto hanya dikirim jika kondisi Rusak (Hilang tidak pernah mengirim foto).
      fotoBukti: _kondisiTemuan == 'Rusak' ? _fotoBukti : null,
    );

    if (!mounted) return;
    setState(() => _isSubmitting = false);

    if (result['success'] == true) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Row(
            children: [
              const Icon(Icons.check_circle_rounded, color: Colors.white, size: 20),
              const SizedBox(width: 8),
              Expanded(child: Text(result['message'] ?? 'Berhasil!')),
            ],
          ),
          backgroundColor: const Color(0xFF10B981),
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          duration: const Duration(seconds: 3),
        ),
      );

      // Reset form
      setState(() {
        _selectedBarang = null;
        _fotoBukti = null;
        _catatanController.clear();
        _codeController.clear();
        _alreadyChecked = false;
        _kondisiTemuan = 'Baik';
      });
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(result['message'] ?? 'Gagal mengirim'),
          backgroundColor: const Color(0xFFEF4444),
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF3F4F6),
      appBar: AppBar(
        title: Text(
          'Inventaris',
          style: GoogleFonts.inter(fontWeight: FontWeight.w800, fontSize: 20),
        ),
        centerTitle: true,
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF4F46E5),
        elevation: 0,
        scrolledUnderElevation: 1,
      ),
      body: ListView(
        controller: _scrollController,
        padding: const EdgeInsets.all(16),
        children: [
          // Header
          Row(
            children: [
              const Icon(Icons.qr_code_2_rounded, color: Color(0xFF4F46E5), size: 26),
              const SizedBox(width: 8),
              Text(
                'Scan & Cek Barang',
                style: GoogleFonts.inter(
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  color: const Color(0xFF1F2937),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Periode info
          if (_periodeInfo != null)
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: const Color(0xFFEEF2FF),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFC7D2FE)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.info_outline_rounded, color: Color(0xFF4F46E5), size: 20),
                  const SizedBox(width: 10),
                  Expanded(
                    child: RichText(
                      text: TextSpan(
                        style: GoogleFonts.inter(fontSize: 13, color: const Color(0xFF374151)),
                        children: [
                          TextSpan(
                            text: 'Periode Aktif: ',
                            style: GoogleFonts.inter(fontWeight: FontWeight.w700),
                          ),
                          TextSpan(text: _periodeInfo),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),

          if (!_hasPeriode)
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: const Color(0xFFFEF3C7),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFFBBF24)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.warning_amber_rounded, color: Color(0xFFD97706), size: 20),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Tidak ada periode pengecekan aktif saat ini.',
                      style: GoogleFonts.inter(fontSize: 13, color: const Color(0xFF92400E)),
                    ),
                  ),
                ],
              ),
            ),

          const SizedBox(height: 16),

          // Scanner Card
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFE5E7EB)),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.03),
                  blurRadius: 12,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Column(
              children: [
                Row(
                  children: [
                    const Icon(Icons.qr_code_scanner_rounded, color: Color(0xFF4F46E5), size: 22),
                    const SizedBox(width: 8),
                    Text(
                      'Identifikasi Aset',
                      style: GoogleFonts.inter(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: const Color(0xFF111827),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),

                // Camera preview
                if (_cameraActive && _scannerController != null)
                  ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: SizedBox(
                      height: 250,
                      child: MobileScanner(
                        controller: _scannerController!,
                        onDetect: _onBarcodeDetected,
                      ),
                    ),
                  ),

                const SizedBox(height: 12),

                // Camera toggle button
                SizedBox(
                  width: double.infinity,
                  height: 50,
                  child: ElevatedButton.icon(
                    onPressed: _toggleCamera,
                    icon: Icon(
                      _cameraActive ? Icons.stop_circle_rounded : Icons.camera_alt_rounded,
                      size: 22,
                    ),
                    label: Text(
                      _cameraActive ? 'Matikan Kamera Scanner' : 'Mulai Kamera Scanner',
                      style: GoogleFonts.inter(fontWeight: FontWeight.w700, fontSize: 14),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _cameraActive ? const Color(0xFFEF4444) : const Color(0xFF4F46E5),
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                      elevation: 0,
                    ),
                  ),
                ),

                const SizedBox(height: 20),

                // Divider with text
                Row(
                  children: [
                    const Expanded(child: Divider(color: Color(0xFFE5E7EB))),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      child: Text(
                        'Atau masukkan Kode Barang secara manual',
                        style: GoogleFonts.inter(fontSize: 11, color: const Color(0xFF9CA3AF)),
                      ),
                    ),
                    const Expanded(child: Divider(color: Color(0xFFE5E7EB))),
                  ],
                ),

                const SizedBox(height: 16),

                // Manual input
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _codeController,
                        style: GoogleFonts.inter(
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                          letterSpacing: 1,
                        ),
                        textAlign: TextAlign.center,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(
                          hintText: 'Contoh: 00005 atau 5',
                          hintStyle: GoogleFonts.inter(
                            color: const Color(0xFF9CA3AF),
                            fontWeight: FontWeight.w400,
                            fontSize: 14,
                          ),
                          filled: true,
                          fillColor: const Color(0xFFF9FAFB),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFF6366F1), width: 2),
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFF6366F1), width: 2),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFF4F46E5), width: 2),
                          ),
                          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                        ),
                        onSubmitted: (v) => _searchBarang(v),
                      ),
                    ),
                    const SizedBox(width: 10),
                    SizedBox(
                      height: 50,
                      child: ElevatedButton.icon(
                        onPressed: _isSearching ? null : () => _searchBarang(_codeController.text),
                        icon: const Icon(Icons.search_rounded, size: 20),
                        label: Text(
                          'Cari',
                          style: GoogleFonts.inter(fontWeight: FontWeight.w700, fontSize: 14),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF4F46E5),
                          foregroundColor: Colors.white,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                          elevation: 0,
                          padding: const EdgeInsets.symmetric(horizontal: 20),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  'Scan QR-code di barang atau ketikkan 5 digit kode barang.',
                  style: GoogleFonts.inter(fontSize: 11, color: const Color(0xFF9CA3AF)),
                  textAlign: TextAlign.center,
                ),
              ],
            ),
          ),

          if (_isSearching) ...[
            const SizedBox(height: 20),
            const Center(child: CircularProgressIndicator(color: Color(0xFF4F46E5))),
          ],

          // Item Detail & Form
          if (_selectedBarang != null) ...[
            const SizedBox(height: 16),
            _buildDetailCard(),
          ],
        ],
      ),
    );
  }

  Widget _buildDetailCard() {
    final barang = _selectedBarang!;
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE5E7EB)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        children: [
          // Header gradient
          Container(
            padding: const EdgeInsets.all(16),
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [Color(0xFF6366F1), Color(0xFF4F46E5)],
              ),
              borderRadius: BorderRadius.vertical(top: Radius.circular(15)),
            ),
            child: Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.2),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.assignment_rounded, color: Colors.white, size: 20),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        barang.namaBarang,
                        style: GoogleFonts.inter(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: Colors.white,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 2),
                      Row(
                        children: [
                          const Icon(Icons.qr_code_2_rounded, color: Colors.white70, size: 14),
                          const SizedBox(width: 4),
                          Text(
                            'Kode: ${barang.formattedCode}',
                            style: GoogleFonts.inter(
                              fontSize: 12,
                              color: Colors.white.withValues(alpha: 0.85),
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
                // Info Grid
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF3F4F6),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    children: [
                      // Foto
                      if (barang.foto != null && barang.foto!.isNotEmpty)
                        Container(
                          margin: const EdgeInsets.only(bottom: 12),
                          height: 120,
                          width: double.infinity,
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(10),
                            child: CachedNetworkImage(
                              imageUrl: ApiConfig.barangImageUrl(barang.foto!),
                              fit: BoxFit.contain,
                              placeholder: (_, __) => Container(
                                color: const Color(0xFFE5E7EB),
                                child: const Center(child: CircularProgressIndicator(strokeWidth: 2)),
                              ),
                              errorWidget: (_, __, ___) => Container(
                                color: const Color(0xFFE5E7EB),
                                child: const Icon(Icons.image_not_supported_outlined, size: 40, color: Color(0xFF9CA3AF)),
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
                      const SizedBox(height: 10),
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
                              barang.statusAktif == 'aktif' ? const Color(0xFF10B981) : const Color(0xFF6B7280),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      _infoItemWithIcon(
                        'LOKASI PENEMPATAN',
                        '${barang.namaUnit} → ${barang.namaRuang}',
                        Icons.location_on_rounded,
                        const Color(0xFFEF4444),
                      ),
                    ],
                  ),
                ),

                // Already checked alert
                if (_alreadyChecked) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFFEEF2FF),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: const Color(0xFFC7D2FE)),
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(Icons.info_outline_rounded, color: Color(0xFF4F46E5), size: 18),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            _checkInfo,
                            style: GoogleFonts.inter(fontSize: 12, color: const Color(0xFF374151)),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],

                const SizedBox(height: 18),

                // Kondisi Fisik Radio
                Text(
                  'Kondisi Fisik Saat Ini *',
                  style: GoogleFonts.inter(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: const Color(0xFF374151),
                  ),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    _buildKondisiButton('Baik', Icons.check_circle_rounded, const Color(0xFF10B981)),
                    const SizedBox(width: 8),
                    _buildKondisiButton('Rusak', Icons.warning_rounded, const Color(0xFFF59E0B)),
                    const SizedBox(width: 8),
                    _buildKondisiButton('Hilang', Icons.cancel_rounded, const Color(0xFFEF4444)),
                  ],
                ),

                // Photo upload — HANYA untuk kondisi "Rusak".
                if (_kondisiTemuan == 'Rusak') ...[
                  const SizedBox(height: 16),
                  Text(
                    'Lampirkan Foto Bukti *',
                    style: GoogleFonts.inter(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: const Color(0xFFEF4444),
                    ),
                  ),
                  const SizedBox(height: 8),
                  if (_fotoBukti != null)
                    Stack(
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(12),
                          child: Image.file(
                            _fotoBukti!,
                            height: 120,
                            width: double.infinity,
                            fit: BoxFit.cover,
                          ),
                        ),
                        Positioned(
                          top: 6,
                          right: 6,
                          child: GestureDetector(
                            onTap: () => setState(() => _fotoBukti = null),
                            child: Container(
                              padding: const EdgeInsets.all(4),
                              decoration: const BoxDecoration(
                                color: Colors.red,
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(Icons.close, color: Colors.white, size: 16),
                            ),
                          ),
                        ),
                      ],
                    ),
                  const SizedBox(height: 8),
                  OutlinedButton.icon(
                    onPressed: _pickImage,
                    icon: const Icon(Icons.camera_alt_rounded, size: 18),
                    label: Text(
                      _fotoBukti != null ? 'Ganti Foto' : 'Ambil Foto / Pilih File',
                      style: GoogleFonts.inter(fontWeight: FontWeight.w600, fontSize: 13),
                    ),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: const Color(0xFF4F46E5),
                      side: const BorderSide(color: Color(0xFF4F46E5)),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      minimumSize: const Size(double.infinity, 0),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Melampirkan foto bukti kondisi fisik wajib untuk temuan barang rusak.',
                    style: GoogleFonts.inter(fontSize: 11, color: const Color(0xFFEF4444), fontWeight: FontWeight.w600),
                  ),
                ],

                const SizedBox(height: 16),

                // Catatan
                Text(
                  'Catatan Temuan Fisik',
                  style: GoogleFonts.inter(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: const Color(0xFF374151),
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _catatanController,
                  maxLines: 3,
                  style: GoogleFonts.inter(fontSize: 13),
                  decoration: InputDecoration(
                    hintText: 'Tuliskan keterangan detail kondisi fisik barang...',
                    hintStyle: GoogleFonts.inter(color: const Color(0xFF9CA3AF), fontSize: 13),
                    filled: true,
                    fillColor: const Color(0xFFF9FAFB),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: Color(0xFFD1D5DB)),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: Color(0xFFD1D5DB)),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: Color(0xFF6366F1), width: 2),
                    ),
                    contentPadding: const EdgeInsets.all(14),
                  ),
                ),

                const SizedBox(height: 20),

                // Submit button
                SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton.icon(
                    onPressed: _isSubmitting ? null : _submitPengecekan,
                    icon: _isSubmitting
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                          )
                        : const Icon(Icons.send_rounded, size: 20),
                    label: Text(
                      _isSubmitting ? 'Memproses...' : 'Kirim Laporan',
                      style: GoogleFonts.inter(fontWeight: FontWeight.w700, fontSize: 14),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF4F46E5),
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                      elevation: 0,
                      disabledBackgroundColor: const Color(0xFF4F46E5).withValues(alpha: 0.6),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildKondisiButton(String label, IconData icon, Color color) {
    final isSelected = _kondisiTemuan == label;
    return Expanded(
      child: GestureDetector(
        onTap: () {
          setState(() {
            _kondisiTemuan = label;
            // Foto bukti cuma relevan untuk "Rusak" — reset kalau pindah ke kondisi lain.
            if (label != 'Rusak') {
              _fotoBukti = null;
            }
          });
        },
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(
            color: isSelected ? color : Colors.transparent,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(
              color: isSelected ? color : color.withValues(alpha: 0.4),
              width: isSelected ? 2 : 1,
            ),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 16, color: isSelected ? Colors.white : color),
              const SizedBox(width: 4),
              Text(
                label,
                style: GoogleFonts.inter(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: isSelected ? Colors.white : color,
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
        return const Color(0xFF10B981);
      case 'Rusak':
        return const Color(0xFFF59E0B);
      case 'Hilang':
        return const Color(0xFFEF4444);
      default:
        return const Color(0xFF6B7280);
    }
  }

  Widget _infoItem(String label, String value) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: GoogleFonts.inter(
            fontSize: 10,
            fontWeight: FontWeight.w700,
            color: const Color(0xFF9CA3AF),
            letterSpacing: 0.5,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          value,
          style: GoogleFonts.inter(
            fontSize: 13,
            fontWeight: FontWeight.w700,
            color: const Color(0xFF111827),
          ),
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
          style: GoogleFonts.inter(
            fontSize: 10,
            fontWeight: FontWeight.w700,
            color: const Color(0xFF9CA3AF),
            letterSpacing: 0.5,
          ),
        ),
        const SizedBox(height: 4),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(6),
          ),
          child: Text(
            value,
            style: GoogleFonts.inter(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: color,
            ),
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
          style: GoogleFonts.inter(
            fontSize: 10,
            fontWeight: FontWeight.w700,
            color: const Color(0xFF9CA3AF),
            letterSpacing: 0.5,
          ),
        ),
        const SizedBox(height: 2),
        Row(
          children: [
            Icon(icon, size: 14, color: iconColor),
            const SizedBox(width: 4),
            Expanded(
              child: Text(
                value,
                style: GoogleFonts.inter(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: const Color(0xFF111827),
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }
}