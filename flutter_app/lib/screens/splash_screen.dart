import 'dart:async';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'login_screen.dart';

const _deepPurple = Color(0xFF3B0764);
const _midPurple = Color(0xFF7C3AED);
const _nearBlack = Color(0xFF0B0512);

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  Timer? _autoNavigateTimer;

  @override
  void initState() {
    super.initState();
    // Splash otomatis pindah ke LoginScreen setelah 12 detik.
    // Slider "Geser untuk Mulai" tetap ada buat yang mau skip lebih cepat.
    _autoNavigateTimer = Timer(const Duration(milliseconds: 12000), _goToLogin);
  }

  @override
  void dispose() {
    _autoNavigateTimer?.cancel();
    super.dispose();
  }

  void _goToLogin() {
    _autoNavigateTimer?.cancel();
    if (!mounted) return;
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _nearBlack,
      body: Stack(
        children: [
          // Background asset (illustrated cube)
          Positioned.fill(
            child: Image.asset(
              'assets/icon/back1.png',
              fit: BoxFit.cover,
              errorBuilder: (context, error, stackTrace) => const ColoredBox(color: _nearBlack),
            ),
          ),
          // Dark gradient overlay biar teks/tombol putih tetap kebaca
          // di atas gambar apa pun
          const Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Color(0x8C000000),
                    Color(0x0D000000),
                    Color(0x26000000),
                    Color(0x99000000),
                  ],
                  stops: [0.0, 0.28, 0.6, 1.0],
                ),
              ),
            ),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Column(
                children: [
                  // Jarak tetap kecil di atas logo (bukan flex) — biar
                  // headline naik lebih dekat ke atas layar, gak ketarik
                  // ke tengah.
                  const SizedBox(height: 45),
                  Row(
                    children: [
                      Container(
                        width: 40,
                        height: 40,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        alignment: Alignment.center,
                        child: RichText(
                          text: TextSpan(
                            style: GoogleFonts.inter(fontSize: 18, fontWeight: FontWeight.w800),
                            children: const [
                              TextSpan(text: 'i', style: TextStyle(color: _nearBlack)),
                              TextSpan(text: 'k', style: TextStyle(color: _midPurple)),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Text(
                        'InventarisKu',
                        style: GoogleFonts.inter(
                          color: Colors.white,
                          fontSize: 20,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.1,
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 40),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'Kelola Semua\nAset dalam\nGenggaman',
                      style: GoogleFonts.inter(
                        color: Colors.white,
                        fontSize: 38,
                        height: 1.2,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.5,
                      ),
                    ),
                  ),

                  // Spacer atas lebih besar dari spacer bawah — jadi pill
                  // "128 Item terpantau" ketarik turun, lebih renggang dari
                  // kubus dan lebih deket ke slider di bawahnya.
                  const Expanded(flex: 5, child: SizedBox()),

                  _StatPill(label: '128 Item terpantau'),

                  const Expanded(flex: 1, child: SizedBox()),

                  // Bottom bar — slider "Geser untuk Mulai" gantiin tombol
                  // biasa. Splash tetap auto-lanjut sendiri lewat timer di atas.
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(40),
                      border: Border.all(color: Colors.white.withValues(alpha: 0.14)),
                    ),
                    child: Row(
                      children: [
                        const _CircleIconButton(icon: Icons.qr_code_scanner_rounded),
                        const SizedBox(width: 10),
                        Expanded(
                          child: _SlideToStart(onCompleted: _goToLogin),
                        ),
                        const SizedBox(width: 10),
                        const _CircleIconButton(icon: Icons.keyboard_arrow_down_rounded),
                      ],
                    ),
                  ),

                  const SizedBox(height: 80),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StatPill extends StatelessWidget {
  final String label;
  const _StatPill({required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
      decoration: BoxDecoration(
        color: Colors.black.withValues(alpha: 0.35),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.white.withValues(alpha: 0.12)),
      ),
      child: Text(
        label,
        style: GoogleFonts.inter(
          color: Colors.white.withValues(alpha: 0.8),
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _CircleIconButton extends StatelessWidget {
  final IconData icon;
  const _CircleIconButton({required this.icon});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 44,
      height: 44,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white.withValues(alpha: 0.12)),
      ),
      child: Icon(icon, color: Colors.white, size: 20),
    );
  }
}

// Slider ala "slide to answer" di iPhone: thumb bulat penuh nempel di
// track (tanpa padding), teks nongol di sebelah kanan thumb dan pelan-
// pelan ketutup pas di-drag. Drag ke kanan sampai ujung buat trigger
// navigasi; kalau dilepas sebelum sampai ujung, thumb balik otomatis.
class _SlideToStart extends StatefulWidget {
  final VoidCallback onCompleted;
  const _SlideToStart({required this.onCompleted});

  @override
  State<_SlideToStart> createState() => _SlideToStartState();
}

class _SlideToStartState extends State<_SlideToStart>
    with TickerProviderStateMixin {
  static const double _trackHeight = 48;

  double _dragX = 0;
  bool _completed = false;
  late final AnimationController _snapController;

  // Animasi hint: icon di dalam thumb nyentak pelan ke kanan berulang,
  // biar kelihatan "ngasih kode" buat digeser. Berhenti pas lagi di-drag
  // atau udah selesai.
  late final AnimationController _hintController;
  late final Animation<double> _hintOffset;

  @override
  void initState() {
    super.initState();
    _snapController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 220),
    );
    _hintController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 700),
    )..repeat(reverse: true);
    _hintOffset = Tween<double>(begin: 0, end: 8).animate(
      CurvedAnimation(parent: _hintController, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _snapController.dispose();
    _hintController.dispose();
    super.dispose();
  }

  void _handleDragStart(DragStartDetails details) {
    if (_completed) return;
    _hintController.stop();
  }

  void _handleDragUpdate(DragUpdateDetails details, double maxDrag) {
    if (_completed) return;
    setState(() {
      _dragX = (_dragX + details.delta.dx).clamp(0.0, maxDrag);
    });
  }

  void _handleDragEnd(double maxDrag) {
    if (_completed || maxDrag <= 0) return;
    if (_dragX >= maxDrag * 0.82) {
      setState(() {
        _completed = true;
        _dragX = maxDrag;
      });
      widget.onCompleted();
    } else {
      _animateBack();
      _hintController.repeat(reverse: true);
    }
  }

  void _animateBack() {
    final start = _dragX;
    _snapController.removeListener(_snapListener);
    _snapController.reset();
    _snapController.addListener(_snapListener);
    _snapListenerStart = start;
    _snapController.forward();
  }

  double _snapListenerStart = 0;
  void _snapListener() {
    setState(() {
      _dragX = _snapListenerStart * (1 - _snapController.value);
    });
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final maxDrag = constraints.maxWidth - _trackHeight;
        final progress = maxDrag > 0 ? (_dragX / maxDrag).clamp(0.0, 1.0) : 0.0;

        return Container(
          height: _trackHeight,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.14),
            borderRadius: BorderRadius.circular(_trackHeight / 2),
            border: Border.all(color: Colors.white.withValues(alpha: 0.1)),
          ),
          child: Stack(
            alignment: Alignment.centerLeft,
            children: [
              // Teks di tengah track secara keseluruhan (bukan di tengah
              // sisa ruang setelah thumb), biar nggak keliatan nge-geser
              // ke kanan. Ada efek shimmer (highlight nyapu) di atasnya.
              Positioned.fill(
                child: Align(
                  alignment: const Alignment(0.15, 0),
                  child: Opacity(
                    opacity: 1 - progress,
                    child: _ShimmerText(
                      text: 'Geser untuk Mulai',
                      style: GoogleFonts.inter(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
              ),
              Positioned(
                left: _dragX,
                child: GestureDetector(
                  onHorizontalDragStart: _handleDragStart,
                  onHorizontalDragUpdate: (d) => _handleDragUpdate(d, maxDrag),
                  onHorizontalDragEnd: (_) => _handleDragEnd(maxDrag),
                  child: Container(
                    width: _trackHeight,
                    height: _trackHeight,
                    decoration: const BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                    ),
                    child: _completed
                        ? const Icon(Icons.check_rounded, color: _deepPurple, size: 22)
                        : AnimatedBuilder(
                            animation: _hintOffset,
                            builder: (context, child) {
                              return Transform.translate(
                                offset: Offset(_hintOffset.value, 0),
                                child: child,
                              );
                            },
                            child: const Icon(
                              Icons.chevron_right_rounded,
                              color: _deepPurple,
                              size: 22,
                            ),
                          ),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

// Teks dengan efek shimmer: highlight terang yang nyapu dari kiri ke
// kanan berulang-ulang di atas teks redup, biar keliatan lebih hidup
// sebagai indikator "geser".
class _ShimmerText extends StatefulWidget {
  final String text;
  final TextStyle style;
  const _ShimmerText({required this.text, required this.style});

  @override
  State<_ShimmerText> createState() => _ShimmerTextState();
}

class _ShimmerTextState extends State<_ShimmerText>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1800),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        return ShaderMask(
          blendMode: BlendMode.srcIn,
          shaderCallback: (bounds) {
            return LinearGradient(
              colors: const [
                Color(0x99FFFFFF),
                Color(0x99FFFFFF),
                Colors.white,
                Color(0x99FFFFFF),
                Color(0x99FFFFFF),
              ],
              stops: const [0.0, 0.35, 0.5, 0.65, 1.0],
              begin: Alignment.centerLeft,
              end: Alignment.centerRight,
              // Highlight-nya digeser tiap frame lewat transform, bukan
              // dengan mindahin stop, jadi sapuan cahayanya mulus.
              transform: _SlidingGradientTransform(
                slidePercent: (_controller.value * 2) - 1,
              ),
            ).createShader(bounds);
          },
          child: child,
        );
      },
      child: Text(widget.text, style: widget.style),
    );
  }
}

class _SlidingGradientTransform extends GradientTransform {
  final double slidePercent;
  const _SlidingGradientTransform({required this.slidePercent});

  @override
  Matrix4? transform(Rect bounds, {TextDirection? textDirection}) {
    return Matrix4.translationValues(bounds.width * slidePercent, 0, 0);
  }
}