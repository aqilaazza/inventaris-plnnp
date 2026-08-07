import 'dart:io';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../services/api_service.dart';
import '../config/api_config.dart';

class UpdateGambarScreen extends StatefulWidget {
  const UpdateGambarScreen({super.key});

  @override
  State<UpdateGambarScreen> createState() => _UpdateGambarScreenState();
}

class _UpdateGambarScreenState extends State<UpdateGambarScreen> {
  List<Map<String, dynamic>> _goodsList = [];
  Map<String, dynamic>? _selectedGoods;
  bool _isLoadingList = true;
  bool _isUploading = false;
  File? _newImageFile;

  @override
  void initState() {
    super.initState();
    _loadGoodsList();
  }

  Future<void> _loadGoodsList() async {
    setState(() => _isLoadingList = true);
    final result = await ApiService.getListBarangAll();
    if (mounted && result['success'] == true) {
      setState(() {
        _goodsList = List<Map<String, dynamic>>.from(result['data'] ?? []);
        _isLoadingList = false;
      });
    } else {
      if (mounted) setState(() => _isLoadingList = false);
    }
  }

  Future<void> _pickNewImage() async {
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
              'Pilih Sumber Gambar',
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
              subtitle: Text('Ambil foto barang baru', style: GoogleFonts.inter(fontSize: 12)),
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
              subtitle: Text('Pilih gambar dari galeri', style: GoogleFonts.inter(fontSize: 12)),
              onTap: () => Navigator.pop(ctx, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );

    if (source != null) {
      final picked = await picker.pickImage(source: source, maxWidth: 1600, imageQuality: 85);
      if (picked != null && mounted) {
        setState(() => _newImageFile = File(picked.path));
      }
    }
  }

  Future<void> _uploadImage() async {
    if (_selectedGoods == null) {
      _showSnackbar('Silakan pilih barang terlebih dahulu.', isError: true);
      return;
    }
    if (_newImageFile == null) {
      _showSnackbar('Silakan pilih file gambar untuk diunggah.', isError: true);
      return;
    }

    setState(() => _isUploading = true);

    final result = await ApiService.updateGambarBarang(
      idBarang: _selectedGoods!['id'],
      subAction: 'upload',
      foto: _newImageFile,
    );

    if (!mounted) return;
    setState(() => _isUploading = false);

    if (result['success'] == true) {
      _showSnackbar(result['message'] ?? 'Gambar berhasil diunggah.');
      setState(() {
        _selectedGoods!['foto'] = result['foto'];
        _newImageFile = null;
      });
      // Refresh list to update cached items
      _loadGoodsList();
    } else {
      _showSnackbar(result['message'] ?? 'Gagal mengunggah gambar.', isError: true);
    }
  }

  Future<void> _deleteImage() async {
    if (_selectedGoods == null) return;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Text('Hapus Gambar Barang?', style: GoogleFonts.inter(fontWeight: FontWeight.w700)),
        content: Text(
          'Yakin hapus gambar barang ${_selectedGoods!['nama_barang']}?',
          style: GoogleFonts.inter(fontSize: 14),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text('Batal', style: GoogleFonts.inter(color: Colors.grey)),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFEF4444)),
            child: Text('Ya, Hapus', style: GoogleFonts.inter(fontWeight: FontWeight.w700, color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      setState(() => _isUploading = true);

      final result = await ApiService.updateGambarBarang(
        idBarang: _selectedGoods!['id'],
        subAction: 'delete',
      );

      if (!mounted) return;
      setState(() => _isUploading = false);

      if (result['success'] == true) {
        _showSnackbar(result['message'] ?? 'Gambar berhasil dihapus.');
        setState(() {
          _selectedGoods!['foto'] = null;
        });
        _loadGoodsList();
      } else {
        _showSnackbar(result['message'] ?? 'Gagal menghapus gambar.', isError: true);
      }
    }
  }

  void _showSnackbar(String msg, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(msg),
        backgroundColor: isError ? const Color(0xFFEF4444) : const Color(0xFF10B981),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF3F4F6),
      appBar: AppBar(
        title: Text(
          'Update Gambar Barang',
          style: GoogleFonts.inter(fontWeight: FontWeight.w800, fontSize: 18),
        ),
        centerTitle: true,
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF4F46E5),
        elevation: 0,
        scrolledUnderElevation: 1,
      ),
      body: _isLoadingList
          ? const Center(child: CircularProgressIndicator(color: Color(0xFF4F46E5)))
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                // Pilih Barang Card
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
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Pilih Barang',
                        style: GoogleFonts.inter(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: const Color(0xFF374151),
                        ),
                      ),
                      const SizedBox(height: 10),
                      
                      // Searchable Dropdown / Picker Trigger
                      GestureDetector(
                        onTap: _showGoodsPickerModal,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF9FAFB),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: const Color(0xFFD1D5DB)),
                          ),
                          child: Row(
                            children: [
                              Expanded(
                                child: Text(
                                  _selectedGoods != null
                                      ? '[${_selectedGoods!['formatted_code']}] ${_selectedGoods!['nama_barang']}'
                                      : '-- Pilih Barang --',
                                  style: GoogleFonts.inter(
                                    fontSize: 14,
                                    fontWeight: _selectedGoods != null ? FontWeight.w600 : FontWeight.w400,
                                    color: _selectedGoods != null ? const Color(0xFF111827) : const Color(0xFF9CA3AF),
                                  ),
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                              const Icon(Icons.arrow_drop_down, color: Color(0xFF6B7280)),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 16),

                // Goods Preview Card
                if (_selectedGoods != null) ...[
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
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Current image or placeholder
                            Container(
                              width: 110,
                              height: 110,
                              decoration: BoxDecoration(
                                color: const Color(0xFFF3F4F6),
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: const Color(0xFFE5E7EB)),
                              ),
                              child: _selectedGoods!['foto'] != null && _selectedGoods!['foto'].toString().isNotEmpty
                                  ? ClipRRect(
                                      borderRadius: BorderRadius.circular(11),
                                      child: CachedNetworkImage(
                                        imageUrl: ApiConfig.barangImageUrl(_selectedGoods!['foto']),
                                        fit: BoxFit.cover,
                                        placeholder: (_, __) => const Center(
                                          child: CircularProgressIndicator(strokeWidth: 2),
                                        ),
                                        errorWidget: (_, __, ___) => const Icon(
                                          Icons.image_not_supported_outlined,
                                          size: 40,
                                          color: Color(0xFF9CA3AF),
                                        ),
                                      ),
                                    )
                                  : const Icon(
                                      Icons.image_outlined,
                                      size: 48,
                                      color: Color(0xFF9CA3AF),
                                    ),
                            ),
                            const SizedBox(width: 14),

                            // Item info
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    _selectedGoods!['nama_barang'] ?? '',
                                    style: GoogleFonts.inter(
                                      fontSize: 16,
                                      fontWeight: FontWeight.w800,
                                      color: const Color(0xFF111827),
                                    ),
                                  ),
                                  const SizedBox(height: 6),
                                  _infoRow('Kode:', _selectedGoods!['formatted_code'] ?? ''),
                                  _infoRow('Kategori:', _selectedGoods!['nama_kategori'] ?? '-'),
                                  _infoRow('Merk:', _selectedGoods!['nama_merk'] ?? '-'),
                                  _infoRow('Lokasi:', '${_selectedGoods!['nama_unit']} → ${_selectedGoods!['nama_ruang']}'),
                                  _infoRow('Kondisi:', _selectedGoods!['kondisi'] ?? '-'),

                                  if (_selectedGoods!['foto'] != null && _selectedGoods!['foto'].toString().isNotEmpty) ...[
                                    const SizedBox(height: 10),
                                    OutlinedButton.icon(
                                      onPressed: _isUploading ? null : _deleteImage,
                                      icon: const Icon(Icons.delete_outline_rounded, size: 16),
                                      label: Text('Hapus Gambar', style: GoogleFonts.inter(fontSize: 12, fontWeight: FontWeight.w700)),
                                      style: OutlinedButton.styleFrom(
                                        foregroundColor: const Color(0xFFEF4444),
                                        side: const BorderSide(color: Color(0xFFEF4444)),
                                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 16),

                  // Form Upload Card
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
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Unggah Gambar Baru',
                          style: GoogleFonts.inter(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: const Color(0xFF374151),
                          ),
                        ),
                        const SizedBox(height: 12),

                        // Preview new image if selected
                        if (_newImageFile != null) ...[
                          Container(
                            height: 160,
                            width: double.infinity,
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: const Color(0xFF6366F1), width: 2),
                            ),
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(10),
                              child: Image.file(
                                _newImageFile!,
                                fit: BoxFit.contain,
                              ),
                            ),
                          ),
                          const SizedBox(height: 12),
                        ],

                        OutlinedButton.icon(
                          onPressed: _pickNewImage,
                          icon: const Icon(Icons.add_a_photo_rounded, size: 18),
                          label: Text(
                            _newImageFile != null ? 'Ganti File Gambar' : 'Pilih / Ambil Gambar',
                            style: GoogleFonts.inter(fontSize: 13, fontWeight: FontWeight.w600),
                          ),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: const Color(0xFF4F46E5),
                            side: const BorderSide(color: Color(0xFF4F46E5)),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            minimumSize: const Size(double.infinity, 0),
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'Format: JPG/PNG/WebP. Server akan mengompresi otomatis.',
                          style: GoogleFonts.inter(fontSize: 11, color: const Color(0xFF9CA3AF)),
                        ),

                        const SizedBox(height: 20),

                        SizedBox(
                          width: double.infinity,
                          height: 50,
                          child: ElevatedButton.icon(
                            onPressed: _isUploading ? null : _uploadImage,
                            icon: _isUploading
                                ? const SizedBox(
                                    width: 20,
                                    height: 20,
                                    child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                                  )
                                : const Icon(Icons.cloud_upload_rounded, size: 20),
                            label: Text(
                              _isUploading ? 'Mengunggah...' : 'Unggah & Kompres',
                              style: GoogleFonts.inter(fontWeight: FontWeight.w700, fontSize: 14),
                            ),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: const Color(0xFF4F46E5),
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              elevation: 0,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
    );
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 3),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '$label ',
            style: GoogleFonts.inter(fontSize: 11, fontWeight: FontWeight.w700, color: const Color(0xFF6B7280)),
          ),
          Expanded(
            child: Text(
              value,
              style: GoogleFonts.inter(fontSize: 11, color: const Color(0xFF111827), fontWeight: FontWeight.w500),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }

  void _showGoodsPickerModal() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        String query = '';
        return StatefulBuilder(
          builder: (context, setModalState) {
            final filtered = _goodsList.where((g) {
              final q = query.toLowerCase();
              final name = (g['nama_barang'] ?? '').toString().toLowerCase();
              final code = (g['formatted_code'] ?? '').toString().toLowerCase();
              return name.contains(q) || code.contains(q);
            }).toList();

            return Container(
              height: MediaQuery.of(context).size.height * 0.75,
              padding: const EdgeInsets.all(20),
              child: Column(
                children: [
                  Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: const Color(0xFFD1D5DB),
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    'Pilih Barang Inventaris',
                    style: GoogleFonts.inter(fontSize: 16, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 12),

                  // Search box
                  TextField(
                    style: GoogleFonts.inter(fontSize: 14),
                    decoration: InputDecoration(
                      hintText: 'Cari nama / kode barang...',
                      prefixIcon: const Icon(Icons.search_rounded, size: 20),
                      filled: true,
                      fillColor: const Color(0xFFF9FAFB),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: Color(0xFFD1D5DB)),
                      ),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    ),
                    onChanged: (v) {
                      setModalState(() => query = v);
                    },
                  ),
                  const SizedBox(height: 12),

                  // Goods list
                  Expanded(
                    child: filtered.isEmpty
                        ? Center(
                            child: Text(
                              'Barang tidak ditemukan.',
                              style: GoogleFonts.inter(fontSize: 13, color: const Color(0xFF9CA3AF)),
                            ),
                          )
                        : ListView.separated(
                            itemCount: filtered.length,
                            separatorBuilder: (_, __) => const Divider(height: 1),
                            itemBuilder: (context, index) {
                              final item = filtered[index];
                              final isSelected = _selectedGoods != null && _selectedGoods!['id'] == item['id'];
                              return ListTile(
                                selected: isSelected,
                                selectedTileColor: const Color(0xFFEEF2FF),
                                leading: Container(
                                  width: 40,
                                  height: 40,
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFEEF2FF),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Center(
                                    child: Icon(
                                      item['foto'] != null ? Icons.image_rounded : Icons.inventory_2_rounded,
                                      color: const Color(0xFF4F46E5),
                                      size: 20,
                                    ),
                                  ),
                                ),
                                title: Text(
                                  '[${item['formatted_code']}] ${item['nama_barang']}',
                                  style: GoogleFonts.inter(
                                    fontSize: 13,
                                    fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                                    color: isSelected ? const Color(0xFF4F46E5) : const Color(0xFF111827),
                                  ),
                                ),
                                subtitle: Text(
                                  '${item['nama_kategori']} | ${item['nama_merk']}',
                                  style: GoogleFonts.inter(fontSize: 11, color: const Color(0xFF6B7280)),
                                ),
                                onTap: () {
                                  setState(() {
                                    _selectedGoods = item;
                                    _newImageFile = null;
                                  });
                                  Navigator.pop(ctx);
                                },
                              );
                            },
                          ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }
}
