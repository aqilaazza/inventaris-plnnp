import 'dart:io';
import 'package:flutter/material.dart';
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
  final Color _primary = const Color(0xFF4F46E5);

  List<Map<String, dynamic>> _goodsList = [];
  bool _isLoadingList = true;
  String _search = '';
  String _filter = 'semua'; // 'semua' | 'ada' | 'belum'

  String _fotoOf(Map<String, dynamic> g) => (g['foto'] ?? '').toString();

  bool _hasFoto(Map<String, dynamic> g) => _fotoOf(g).isNotEmpty;

  int get _totalBarang => _goodsList.length;

  int get _totalAda => _goodsList.where(_hasFoto).length;

  int get _totalBelum => _totalBarang - _totalAda;

  double get _progress => _totalBarang == 0 ? 0 : _totalAda / _totalBarang;

  List<Map<String, dynamic>> get _filtered {
    final q = _search.toLowerCase().trim();
    return _goodsList.where((g) {
      final matchesSearch = q.isEmpty ||
          (g['nama_barang'] ?? '').toString().toLowerCase().contains(q) ||
          (g['formatted_code'] ?? '').toString().toLowerCase().contains(q);
      if (!matchesSearch) return false;
      if (_filter == 'ada') return _hasFoto(g);
      if (_filter == 'belum') return !_hasFoto(g);
      return true;
    }).toList();
  }

  @override
  void initState() {
    super.initState();
    _loadGoodsList();
  }

  Future<void> _loadGoodsList() async {
    setState(() => _isLoadingList = true);
    final result = await ApiService.getListBarangAll();
    if (mounted) {
      setState(() {
        _isLoadingList = false;
        if (result['success'] == true) {
          _goodsList = List<Map<String, dynamic>>.from(result['data'] ?? []);
        }
      });
    }
  }

  Future<void> _openDetail(Map<String, dynamic> barang) async {
    await Navigator.of(context).push(
      PageRouteBuilder(
        pageBuilder: (_, _, _) => _GambarDetailScreen(barang: barang),
        transitionsBuilder: (_, animation, _, child) {
          return FadeTransition(opacity: animation, child: child);
        },
        transitionDuration: const Duration(milliseconds: 220),
      ),
    );
    if (mounted) setState(() {});
  }

  Widget _img(String foto, {BoxFit fit = BoxFit.cover}) {
    return CachedNetworkImage(
      imageUrl: ApiConfig.barangImageUrl(foto),
      fit: fit,
      placeholder: (_, _) => Container(
        color: const Color(0xFFEDEFF5),
        child: const Center(child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF4F46E5))),
      ),
      errorWidget: (_, _, _) => Container(
        color: const Color(0xFFEDEFF5),
        child: const Center(child: Icon(Icons.broken_image_outlined, color: Color(0xFF9CA3AF), size: 32)),
      ),
    );
  }

  Widget _chip(String text, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.15), blurRadius: 6, offset: const Offset(0, 2)),
        ],
      ),
      child: Text(
        text,
        style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Colors.white),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F6FA),
      appBar: AppBar(
        title: const Text('Update Gambar Barang'),
        centerTitle: true,
        backgroundColor: Colors.white,
        foregroundColor: _primary,
        elevation: 0,
        scrolledUnderElevation: 1,
        actions: [
          IconButton(
            tooltip: 'Muat ulang',
            onPressed: _isLoadingList ? null : _loadGoodsList,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: AnimatedSwitcher(
        duration: const Duration(milliseconds: 300),
        child: _isLoadingList
            ? _buildSkeleton(key: const ValueKey('loading'))
            : RefreshIndicator(
                key: const ValueKey('content'),
                onRefresh: _loadGoodsList,
                color: _primary,
                child: CustomScrollView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  slivers: [
                    SliverToBoxAdapter(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _buildHeaderCard(),
                            const SizedBox(height: 16),
                            _buildSearchField(),
                            const SizedBox(height: 12),
                            _buildFilterChips(),
                            const SizedBox(height: 4),
                          ],
                        ),
                      ),
                    ),
                    if (_goodsList.isEmpty)
                      SliverFillRemaining(hasScrollBody: false, child: _buildEmpty())
                    else if (_filtered.isEmpty)
                      SliverFillRemaining(hasScrollBody: false, child: _buildNoResult())
                    else
                      SliverPadding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        sliver: SliverGrid(
                          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2,
                            mainAxisSpacing: 12,
                            crossAxisSpacing: 12,
                            mainAxisExtent: 200,
                          ),
                          delegate: SliverChildBuilderDelegate(
                            (_, index) => _buildGridCard(_filtered[index]),
                            childCount: _filtered.length,
                          ),
                        ),
                      ),
                    const SliverToBoxAdapter(child: SizedBox(height: 16)),
                  ],
                ),
              ),
      ),
    );
  }

  Widget _buildSkeleton({Key? key}) {
    return ListView(
      key: key,
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      children: [
        _PulseBox(height: 120, radius: 16),
        const SizedBox(height: 16),
        _PulseBox(height: 50, radius: 14),
        const SizedBox(height: 12),
        _PulseBox(height: 34, radius: 17),
        const SizedBox(height: 12),
        Row(
          children: const [
            Expanded(child: _PulseBox(height: 200, radius: 16)),
            SizedBox(width: 12),
            Expanded(child: _PulseBox(height: 200, radius: 16)),
          ],
        ),
      ],
    );
  }

  Widget _buildHeaderCard() {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF312E81), Color(0xFF4C1D95), Color(0xFF6D28D9)],
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF4C1D95).withValues(alpha: 0.3),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        children: [
          Row(
            children: [
              SizedBox(
                width: 76,
                height: 76,
                child: Stack(
                  alignment: Alignment.center,
                  children: [
                    SizedBox(
                      width: 76,
                      height: 76,
                      child: CircularProgressIndicator(
                        value: _progress,
                        strokeWidth: 7,
                        backgroundColor: Colors.white.withValues(alpha: 0.2),
                        valueColor: const AlwaysStoppedAnimation<Color>(Color(0xFFA7F3D0)),
                      ),
                    ),
                    Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          '${(_progress * 100).round()}%',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const Text(
                          'lengkap',
                          style: TextStyle(color: Colors.white70, fontSize: 9),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Kelengkapan Foto Barang',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      '$_totalAda dari $_totalBarang barang sudah memiliki foto dokumentasi.',
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.75),
                        fontSize: 12,
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              _headerStat(_totalBarang.toString(), 'Barang', Colors.white),
              const SizedBox(width: 10),
              _headerStat(_totalAda.toString(), 'Ada Foto', const Color(0xFFA7F3D0)),
              const SizedBox(width: 10),
              _headerStat(_totalBelum.toString(), 'Belum Foto', const Color(0xFFFDE68A)),
            ],
          ),
        ],
      ),
    );
  }

  Widget _headerStat(String value, String label, Color valueColor) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.15),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.white.withValues(alpha: 0.2)),
        ),
        child: Column(
          children: [
            Text(
              value,
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w800,
                color: valueColor,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w600,
                color: Colors.white.withValues(alpha: 0.8),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSearchField() {
    return TextField(
      onChanged: (v) => setState(() => _search = v),
      style: const TextStyle(fontSize: 14),
      decoration: InputDecoration(
        hintText: 'Cari nama / kode barang...',
        hintStyle: const TextStyle(color: Color(0xFF9CA3AF)),
        prefixIcon: const Icon(Icons.search_rounded, size: 22, color: Color(0xFF9CA3AF)),
        suffixIcon: _search.isNotEmpty
            ? IconButton(
                icon: const Icon(Icons.clear_rounded, size: 20),
                onPressed: () => setState(() => _search = ''),
              )
            : null,
        filled: true,
        fillColor: Colors.white,
        isDense: true,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: Color(0xFF4F46E5), width: 1.6),
        ),
      ),
    );
  }

  Widget _buildFilterChips() {
    return Row(
      children: [
        _filterChip('semua', 'Semua', '$_totalBarang'),
        const SizedBox(width: 8),
        _filterChip('ada', 'Ada Foto', '$_totalAda'),
        const SizedBox(width: 8),
        _filterChip('belum', 'Belum Foto', '$_totalBelum'),
      ],
    );
  }

  Widget _filterChip(String value, String label, String count) {
    final active = _filter == value;
    return ChoiceChip(
      label: Text('$label  $count'),
      labelStyle: TextStyle(
        fontSize: 12,
        fontWeight: FontWeight.w700,
        color: active ? Colors.white : const Color(0xFF6B7280),
      ),
      selected: active,
      selectedColor: _primary,
      backgroundColor: Colors.white,
      side: BorderSide(color: active ? _primary : const Color(0xFFE5E7EB)),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      onSelected: (_) => setState(() => _filter = value),
    );
  }

  Widget _buildGridCard(Map<String, dynamic> g) {
    final hasFoto = _hasFoto(g);
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      clipBehavior: Clip.antiAlias,
      elevation: 0,
      child: InkWell(
        onTap: () => _openDetail(g),
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (hasFoto)
              Hero(tag: 'photo-${g['id']}', child: _img(_fotoOf(g)))
            else
              Container(
                color: const Color(0xFFEDEFF5),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.image_outlined, color: Color(0xFFC3C8D4), size: 44),
                    const SizedBox(height: 6),
                    Text(
                      'Belum ada foto',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: Colors.grey.shade400,
                      ),
                    ),
                  ],
                ),
              ),
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              height: 70,
              child: IgnorePointer(
                child: Container(
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [Colors.transparent, Colors.black54],
                    ),
                  ),
                ),
              ),
            ),
            Positioned(
              top: 8,
              right: 8,
              child: _chip(
                hasFoto ? 'Ada Foto' : 'Belum Foto',
                hasFoto ? const Color(0xFF10B981) : const Color(0xFF9CA3AF),
              ),
            ),
            Positioned(
              left: 12,
              right: 12,
              bottom: 10,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    g['nama_barang'] ?? '',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12.5,
                      fontWeight: FontWeight.w700,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    g['formatted_code'] ?? '',
                    style: const TextStyle(color: Colors.white70, fontSize: 10),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmpty() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.inventory_2_outlined, size: 56, color: Color(0xFFC3C8D4)),
          const SizedBox(height: 12),
          const Text(
            'Belum ada data barang',
            style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: Color(0xFF374151)),
          ),
          const SizedBox(height: 6),
          Text(
            'Tarik ke bawah untuk memuat ulang.',
            style: TextStyle(fontSize: 12, color: Colors.grey.shade500),
          ),
          const SizedBox(height: 16),
          ElevatedButton.icon(
            onPressed: _loadGoodsList,
            icon: const Icon(Icons.refresh_rounded, size: 18),
            label: const Text('Muat Ulang'),
            style: ElevatedButton.styleFrom(
              backgroundColor: _primary,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildNoResult() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.search_off_rounded, size: 56, color: Color(0xFFC3C8D4)),
          const SizedBox(height: 12),
          const Text(
            'Barang tidak ditemukan',
            style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: Color(0xFF374151)),
          ),
          const SizedBox(height: 6),
          Text(
            'Coba kata kunci lain atau ubah filter.',
            style: TextStyle(fontSize: 12, color: Colors.grey.shade500),
          ),
        ],
      ),
    );
  }
}

class _PulseBox extends StatefulWidget {
  final double height;
  final double radius;

  const _PulseBox({required this.height, required this.radius});

  @override
  State<_PulseBox> createState() => _PulseBoxState();
}

class _PulseBoxState extends State<_PulseBox> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: Tween(begin: 0.45, end: 1.0).animate(_controller),
      child: Container(
        height: widget.height,
        decoration: BoxDecoration(
          color: const Color(0xFFE8EAF1),
          borderRadius: BorderRadius.circular(widget.radius),
        ),
      ),
    );
  }
}

class _GambarDetailScreen extends StatefulWidget {
  final Map<String, dynamic> barang;

  const _GambarDetailScreen({required this.barang});

  @override
  State<_GambarDetailScreen> createState() => _GambarDetailScreenState();
}

class _GambarDetailScreenState extends State<_GambarDetailScreen> {
  final Color _primary = const Color(0xFF4F46E5);

  Map<String, dynamic> get _barang => widget.barang;

  String get _foto => (_barang['foto'] ?? '').toString();

  bool get _hasFoto => _foto.isNotEmpty;

  bool _isUploading = false;
  File? _newImageFile;

  void _showMessage(String msg, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(msg),
        backgroundColor: isError ? const Color(0xFFEF4444) : const Color(0xFF10B981),
        behavior: SnackBarBehavior.floating,
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

  Future<void> _pickImage(ImageSource source) async {
    final picked = await ImagePicker().pickImage(source: source, maxWidth: 1600, imageQuality: 85);
    if (picked != null && mounted) {
      setState(() => _newImageFile = File(picked.path));
    }
  }

  Future<void> _uploadImage() async {
    if (_newImageFile == null) {
      _showMessage('Silakan pilih file gambar terlebih dahulu.', isError: true);
      return;
    }
    setState(() => _isUploading = true);

    final result = await ApiService.updateGambarBarang(
      idBarang: _barang['id'],
      subAction: 'upload',
      foto: _newImageFile,
    );

    if (!mounted) return;
    setState(() {
      _isUploading = false;
      if (result['success'] == true) {
        _barang['foto'] = result['foto'];
        _newImageFile = null;
      }
    });

    _showMessage(
      result['success'] == true
          ? (result['message'] ?? 'Gambar berhasil diunggah.')
          : (result['message'] ?? 'Gagal mengunggah gambar.'),
      isError: result['success'] != true,
    );
  }

  Future<void> _deleteImage() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Hapus Gambar?'),
        content: Text('Yakin ingin menghapus foto barang ${_barang['nama_barang']}?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Hapus', style: TextStyle(color: Color(0xFFEF4444), fontWeight: FontWeight.w700)),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    setState(() => _isUploading = true);

    final result = await ApiService.updateGambarBarang(
      idBarang: _barang['id'],
      subAction: 'delete',
    );

    if (!mounted) return;
    setState(() {
      _isUploading = false;
      if (result['success'] == true) {
        _barang['foto'] = null;
      }
    });

    _showMessage(
      result['success'] == true
          ? (result['message'] ?? 'Gambar berhasil dihapus.')
          : (result['message'] ?? 'Gagal menghapus gambar.'),
      isError: result['success'] != true,
    );
  }

  void _viewPhoto() {
    if (!_hasFoto) return;
    showDialog(
      context: context,
      builder: (ctx) => Dialog.fullscreen(
        backgroundColor: Colors.black,
        child: Stack(
          children: [
            Positioned.fill(
              child: InteractiveViewer(
                maxScale: 5,
                child: Center(
                  child: CachedNetworkImage(
                    imageUrl: ApiConfig.barangImageUrl(_foto),
                    fit: BoxFit.contain,
                    placeholder: (_, _) => const CircularProgressIndicator(color: Colors.white),
                    errorWidget: (_, _, _) => const Icon(Icons.broken_image_outlined, color: Colors.white54, size: 60),
                  ),
                ),
              ),
            ),
            SafeArea(
              child: Align(
                alignment: Alignment.topRight,
                child: Padding(
                  padding: const EdgeInsets.all(8),
                  child: IconButton(
                    onPressed: () => Navigator.pop(ctx),
                    icon: const Icon(Icons.close, color: Colors.white, size: 30),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F6FA),
      appBar: AppBar(
        title: const Text('Detail Barang'),
        centerTitle: true,
        backgroundColor: Colors.white,
        foregroundColor: _primary,
        elevation: 0,
        scrolledUnderElevation: 1,
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _buildPhotoCard(),
          const SizedBox(height: 16),
          _buildInfoCard(),
          const SizedBox(height: 16),
          _buildUploadCard(),
          if (_hasFoto) ...[
            const SizedBox(height: 16),
            _buildDeleteCard(),
          ],
          const SizedBox(height: 16),
        ],
      ),
    );
  }

  Widget _buildPhotoCard() {
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: _hasFoto ? const Color(0xFF10B981) : const Color(0xFFE5E7EB),
          width: _hasFoto ? 1.5 : 1,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          SizedBox(
            height: 230,
            width: double.infinity,
            child: _hasFoto
                ? Hero(tag: 'photo-${_barang['id']}', child: _photoImage())
                : Container(
                    color: const Color(0xFFEDEFF5),
                    child: const Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.image_outlined, color: Color(0xFFC3C8D4), size: 56),
                          SizedBox(height: 8),
                          Text(
                            'Barang ini belum memiliki foto',
                            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF9CA3AF)),
                          ),
                        ],
                      ),
                    ),
                  ),
          ),
          Positioned(
            top: 12,
            left: 12,
            child: _chip(
              _hasFoto ? 'Sudah ada foto' : 'Belum ada foto',
              _hasFoto ? const Color(0xFF10B981) : const Color(0xFF9CA3AF),
            ),
          ),
          if (_hasFoto)
            Positioned(
              bottom: 12,
              right: 12,
              child: Material(
                color: Colors.black.withValues(alpha: 0.45),
                shape: const CircleBorder(),
                child: InkWell(
                  onTap: _viewPhoto,
                  customBorder: const CircleBorder(),
                  child: const Padding(
                    padding: EdgeInsets.all(9),
                    child: Icon(Icons.zoom_in_rounded, color: Colors.white, size: 20),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _photoImage() {
    return CachedNetworkImage(
      imageUrl: ApiConfig.barangImageUrl(_foto),
      fit: BoxFit.cover,
      placeholder: (_, _) => Container(
        color: const Color(0xFFEDEFF5),
        child: const Center(child: CircularProgressIndicator(strokeWidth: 2.5, color: Color(0xFF4F46E5))),
      ),
      errorWidget: (_, _, _) => Container(
        color: const Color(0xFFEDEFF5),
        child: const Center(child: Icon(Icons.broken_image_outlined, color: Color(0xFF9CA3AF), size: 48)),
      ),
    );
  }

  Widget _chip(String text, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.15), blurRadius: 6, offset: const Offset(0, 2)),
        ],
      ),
      child: Text(
        text,
        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Colors.white),
      ),
    );
  }

  Widget _buildInfoCard() {
    final kondisi = (_barang['kondisi'] ?? '-').toString();
    return _card(
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            _barang['nama_barang'] ?? '',
            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF111827)),
          ),
          const SizedBox(height: 2),
          Text(
            _barang['formatted_code'] ?? '',
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: _primary),
          ),
          const Divider(height: 24),
          _infoRow(Icons.category_outlined, 'Kategori', (_barang['nama_kategori'] ?? '-').toString()),
          _infoRow(Icons.copyright_outlined, 'Merk', (_barang['nama_merk'] ?? '-').toString()),
          _infoRow(
            Icons.location_on_outlined,
            'Lokasi',
            '${_barang['nama_unit'] ?? '-'} → ${_barang['nama_ruang'] ?? '-'}',
          ),
          _infoRowWidget(
            Icons.build_circle_outlined,
            'Kondisi',
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: _kondisiColor(kondisi).withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                kondisi,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: _kondisiColor(kondisi),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _infoRow(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: const Color(0xFFEEF2FF),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: _primary, size: 17),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              label,
              style: const TextStyle(fontSize: 12.5, color: Color(0xFF6B7280), fontWeight: FontWeight.w600),
            ),
          ),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: const TextStyle(fontSize: 12.5, color: Color(0xFF111827), fontWeight: FontWeight.w700),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }

  Widget _infoRowWidget(IconData icon, String label, Widget valueWidget) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: const Color(0xFFEEF2FF),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: _primary, size: 17),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              label,
              style: const TextStyle(fontSize: 12.5, color: Color(0xFF6B7280), fontWeight: FontWeight.w600),
            ),
          ),
          valueWidget,
        ],
      ),
    );
  }

  Widget _buildUploadCard() {
    return _card(
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Unggah / Ganti Foto', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800)),
          const SizedBox(height: 4),
          const Text(
            'Foto akan otomatis dikompres oleh server.',
            style: TextStyle(fontSize: 11, color: Color(0xFF9CA3AF)),
          ),
          const SizedBox(height: 14),
          if (_newImageFile != null) ...[
            Container(
              height: 170,
              width: double.infinity,
              decoration: BoxDecoration(
                border: Border.all(color: _primary, width: 2),
                borderRadius: BorderRadius.circular(12),
              ),
              clipBehavior: Clip.antiAlias,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  Image.file(_newImageFile!, fit: BoxFit.contain),
                  Positioned(
                    top: 8,
                    right: 8,
                    child: InkWell(
                      onTap: () => setState(() => _newImageFile = null),
                      borderRadius: BorderRadius.circular(18),
                      child: const CircleAvatar(
                        radius: 16,
                        backgroundColor: Color(0xFFEF4444),
                        child: Icon(Icons.close_rounded, color: Colors.white, size: 16),
                      ),
                    ),
                  ),
                  Positioned(
                    bottom: 8,
                    left: 8,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: _primary,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: const Text(
                        'Gambar baru',
                        style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Colors.white),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _pickImage(ImageSource.camera),
                    icon: const Icon(Icons.camera_alt_outlined, size: 18),
                    label: const Text('Kamera'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: _primary,
                      side: const BorderSide(color: Color(0xFFC7D2FE)),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _pickImage(ImageSource.gallery),
                    icon: const Icon(Icons.photo_library_outlined, size: 18),
                    label: const Text('Galeri'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: _primary,
                      side: const BorderSide(color: Color(0xFFC7D2FE)),
                    ),
                  ),
                ),
              ],
            ),
          ] else ...[
            Row(
              children: [
                Expanded(
                  child: _sourceButton(
                    Icons.camera_alt_outlined,
                    'Ambil Foto',
                    () => _pickImage(ImageSource.camera),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _sourceButton(
                    Icons.photo_library_outlined,
                    'Dari Galeri',
                    () => _pickImage(ImageSource.gallery),
                  ),
                ),
              ],
            ),
          ],
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            height: 48,
            child: ElevatedButton.icon(
              onPressed: (_isUploading || _newImageFile == null) ? null : _uploadImage,
              icon: _isUploading
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                    )
                  : const Icon(Icons.cloud_upload_outlined, size: 20),
              label: Text(
                _isUploading ? 'Mengunggah...' : 'Unggah & Kompres',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: _primary,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                elevation: 0,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _sourceButton(IconData icon, String label, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 18),
        decoration: BoxDecoration(
          color: const Color(0xFFF5F3FF),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFC7D2FE)),
        ),
        child: Column(
          children: [
            Icon(icon, color: _primary, size: 26),
            const SizedBox(height: 6),
            Text(
              label,
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF374151)),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDeleteCard() {
    return _card(
      SizedBox(
        width: double.infinity,
        child: OutlinedButton.icon(
          onPressed: _isUploading ? null : _deleteImage,
          icon: _isUploading
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFFEF4444)),
                )
              : const Icon(Icons.delete_outline_rounded, size: 18),
          label: Text(_isUploading ? 'Menghapus...' : 'Hapus Foto Ini'),
          style: OutlinedButton.styleFrom(
            foregroundColor: const Color(0xFFEF4444),
            side: const BorderSide(color: Color(0xFFEF4444)),
            padding: const EdgeInsets.symmetric(vertical: 12),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        ),
      ),
    );
  }

  Widget _card(Widget child) {
    return Container(
      padding: const EdgeInsets.all(16),
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
      child: child,
    );
  }
}
