import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../models/user_model.dart';
import '../../providers/auth_provider.dart';

/// Écran de sélection du concours ciblé
class ConcoursSelectionScreen extends StatefulWidget {
  const ConcoursSelectionScreen({super.key});
  @override
  State<ConcoursSelectionScreen> createState() => _ConcoursSelectionScreenState();
}

class _ConcoursSelectionScreenState extends State<ConcoursSelectionScreen> {
  String? _selectedKey;
  bool _saving = false;

  static const _primary = Color(0xFF5C35D9);
  static const _gold    = Color(0xFFF0B429);

  Future<void> _confirmSelection() async {
    if (_selectedKey == null) return;
    setState(() => _saving = true);

    final auth = context.read<AuthProvider>();
    await auth.updateConcours(_selectedKey!);

    if (!mounted) return;
    Navigator.pushReplacementNamed(context, '/chat');
  }

  @override
  Widget build(BuildContext context) {
    final auth      = context.watch<AuthProvider>();
    final concours  = ConcoursModel.allConcours;
    final isFirstTime = auth.user?.concoursCible == null;

    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft, end: Alignment.bottomRight,
            colors: [Color(0xFF0D0B1F), Color(0xFF1A0A3B)],
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              // Header
              Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  children: [
                    if (!isFirstTime)
                      Align(
                        alignment: Alignment.centerLeft,
                        child: IconButton(
                          onPressed: () => Navigator.pop(context),
                          icon: const Icon(Icons.close, color: Colors.white),
                        ),
                      ),
                    const SizedBox(height: 8),
                    const Text(
                      '🎯 Quel est ton concours ?',
                      style: TextStyle(
                        color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Choisis ton concours cible pour que le Grand Frère personnalise ta préparation.',
                      style: TextStyle(color: Color(0xFF9D96C0), fontSize: 14),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),

              // Grille de sélection
              Expanded(
                child: GridView.builder(
                  padding:     const EdgeInsets.symmetric(horizontal: 16),
                  gridDelegate:const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount:   2,
                    childAspectRatio: 1.1,
                    crossAxisSpacing: 12,
                    mainAxisSpacing:  12,
                  ),
                  itemCount: concours.length,
                  itemBuilder: (context, index) {
                    final c          = concours[index];
                    final isSelected = _selectedKey == c.key;

                    return GestureDetector(
                      onTap: () => setState(() => _selectedKey = c.key),
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        decoration: BoxDecoration(
                          gradient: isSelected
                              ? const LinearGradient(
                                  colors: [_primary, Color(0xFF9B59B6)],
                                  begin: Alignment.topLeft,
                                  end:   Alignment.bottomRight,
                                )
                              : null,
                          color:  isSelected ? null : const Color(0xFF231E42),
                          border: Border.all(
                            color:  isSelected ? _primary : const Color(0xFF3A3060),
                            width:  isSelected ? 2 : 1,
                          ),
                          borderRadius: BorderRadius.circular(16),
                          boxShadow: isSelected
                              ? [BoxShadow(
                                  color:   _primary.withOpacity(0.4),
                                  blurRadius: 12,
                                  spreadRadius: 2,
                                )]
                              : null,
                        ),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(c.emoji, style: const TextStyle(fontSize: 36)),
                            const SizedBox(height: 8),
                            Text(
                              c.nom,
                              style: TextStyle(
                                color:      isSelected ? Colors.white : const Color(0xFFBBB5D8),
                                fontWeight: FontWeight.w700,
                                fontSize:   13,
                              ),
                              textAlign: TextAlign.center,
                            ),
                            const SizedBox(height: 4),
                            Text(
                              c.durationPrep,
                              style: TextStyle(
                                color:   isSelected ? Colors.white70 : const Color(0xFF6B6490),
                                fontSize:11,
                              ),
                            ),
                            if (isSelected)
                              const Padding(
                                padding: EdgeInsets.only(top: 6),
                                child: Icon(Icons.check_circle, color: Colors.white, size: 18),
                              ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
              ),

              // Bouton confirmer
              Padding(
                padding: const EdgeInsets.all(20),
                child: SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: (_selectedKey != null && !_saving) ? _confirmSelection : null,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _selectedKey != null ? _gold : const Color(0xFF3A3060),
                    ),
                    child: _saving
                        ? const SizedBox(
                            width: 24, height: 24,
                            child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                          )
                        : Text(
                            _selectedKey != null
                                ? 'Confirmer et démarrer 🚀'
                                : 'Sélectionne un concours',
                            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700),
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
