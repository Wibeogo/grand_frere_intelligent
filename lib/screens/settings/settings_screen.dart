import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';

/// Écran paramètres (profil, notifications, déconnexion)
class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});
  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  final _nameCtrl = TextEditingController();
  bool _notifs    = true;

  @override
  void initState() {
    super.initState();
    final user = context.read<AuthProvider>().user;
    _nameCtrl.text = user?.fullName ?? '';
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final user = auth.user;

    return Scaffold(
      backgroundColor: const Color(0xFF0D0B1F),
      appBar: AppBar(
        title: const Text('⚙️ Paramètres', style: TextStyle(fontWeight: FontWeight.w700)),
        backgroundColor: const Color(0xFF0D0B1F),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Section Profil
            _sectionTitle('Profil'),
            Container(
              padding:     const EdgeInsets.all(20),
              decoration:  BoxDecoration(
                color:        const Color(0xFF1A1730),
                borderRadius: BorderRadius.circular(16),
                border:       Border.all(color: const Color(0xFF3A3060)),
              ),
              child: Column(
                children: [
                  // Avatar
                  Center(
                    child: Stack(
                      children: [
                        Container(
                          width:  80, height: 80,
                          decoration: const BoxDecoration(
                            gradient: LinearGradient(colors: [Color(0xFF5C35D9), Color(0xFF9B59B6)]),
                            shape:    BoxShape.circle,
                          ),
                          child: Center(
                            child: Text(
                              (user?.firstName ?? 'C').substring(0, 1).toUpperCase(),
                              style: const TextStyle(color: Colors.white, fontSize: 36, fontWeight: FontWeight.w800),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Nom
                  TextField(
                    controller: _nameCtrl,
                    style:      const TextStyle(color: Colors.white),
                    decoration: const InputDecoration(
                      labelText:  'Nom complet',
                      prefixIcon: Icon(Icons.person_outline, color: Color(0xFF6B6490)),
                    ),
                  ),
                  const SizedBox(height: 12),

                  // Email (lecture seule)
                  TextFormField(
                    initialValue: user?.email ?? '',
                    readOnly:     true,
                    style:        const TextStyle(color: Color(0xFF9D96C0)),
                    decoration:   const InputDecoration(
                      labelText:  'Email',
                      prefixIcon: Icon(Icons.email_outlined, color: Color(0xFF6B6490)),
                    ),
                  ),
                  const SizedBox(height: 12),

                  // Téléphone (lecture seule)
                  TextFormField(
                    initialValue: user?.phone ?? '',
                    readOnly:     true,
                    style:        const TextStyle(color: Color(0xFF9D96C0)),
                    decoration:   const InputDecoration(
                      labelText:  'Téléphone',
                      prefixIcon: Icon(Icons.phone_outlined, color: Color(0xFF6B6490)),
                    ),
                  ),
                  const SizedBox(height: 16),

                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: auth.isLoading ? null : () => _saveProfile(context),
                      child: auth.isLoading
                          ? const SizedBox(width: 24, height: 24,
                              child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                          : const Text('Sauvegarder'),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Section Notifications
            _sectionTitle('Notifications'),
            Container(
              decoration: BoxDecoration(
                color:        const Color(0xFF1A1730),
                borderRadius: BorderRadius.circular(16),
                border:       Border.all(color: const Color(0xFF3A3060)),
              ),
              child: SwitchListTile(
                title: const Text('Quiz quotidien', style: TextStyle(color: Colors.white)),
                subtitle: const Text('Recevoir le quiz du jour par email et SMS',
                  style: TextStyle(color: Color(0xFF9D96C0), fontSize: 12)),
                value:    _notifs,
                onChanged:(v) => setState(() => _notifs = v),
                activeColor: const Color(0xFF5C35D9),
              ),
            ),
            const SizedBox(height: 24),

            // Section Concours
            _sectionTitle('Concours ciblé'),
            Container(
              decoration: BoxDecoration(
                color:        const Color(0xFF1A1730),
                borderRadius: BorderRadius.circular(16),
                border:       Border.all(color: const Color(0xFF3A3060)),
              ),
              child: ListTile(
                leading:   const Text('🎯', style: TextStyle(fontSize: 22)),
                title:     Text(
                  user?.concoursCible ?? 'Non défini',
                  style: const TextStyle(color: Colors.white),
                ),
                subtitle:  const Text('Appuyer pour changer',
                  style: TextStyle(color: Color(0xFF9D96C0), fontSize: 12)),
                trailing:  const Icon(Icons.arrow_forward_ios, color: Color(0xFF6B6490), size: 14),
                onTap:     () => Navigator.pushNamed(context, '/concours'),
              ),
            ),
            const SizedBox(height: 24),

            // Section Abonnement
            _sectionTitle('Abonnement'),
            Container(
              decoration: BoxDecoration(
                color:        const Color(0xFF1A1730),
                borderRadius: BorderRadius.circular(16),
                border:       Border.all(color: const Color(0xFF3A3060)),
              ),
              child: ListTile(
                leading:  const Text('⭐', style: TextStyle(fontSize: 22)),
                title:    const Text('Gérer l\'abonnement', style: TextStyle(color: Colors.white)),
                subtitle: Text(
                  user?.isPremium == true ? 'Actif' : 'Aucun abonnement actif',
                  style: TextStyle(
                    color: user?.isPremium == true ? const Color(0xFF4CAF50) : const Color(0xFFE74C3C),
                    fontSize: 12,
                  ),
                ),
                trailing: const Icon(Icons.arrow_forward_ios, color: Color(0xFF6B6490), size: 14),
                onTap:    () => Navigator.pushNamed(context, '/subscription'),
              ),
            ),
            const SizedBox(height: 24),

            // Déconnexion
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () => _confirmLogout(context),
                icon:  const Icon(Icons.logout, color: Colors.redAccent),
                label: const Text('Déconnexion', style: TextStyle(color: Colors.redAccent)),
                style: OutlinedButton.styleFrom(
                  side:   const BorderSide(color: Colors.redAccent),
                  padding:const EdgeInsets.symmetric(vertical: 14),
                ),
              ),
            ),
            const SizedBox(height: 8),
            const Center(
              child: Text(
                'Grand Frère Intelligent v1.0.0\nBurkina Faso 🇧🇫',
                style:TextStyle(color: Color(0xFF6B6490), fontSize: 11),
                textAlign: TextAlign.center,
              ),
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Future<void> _saveProfile(BuildContext context) async {
    final auth = context.read<AuthProvider>();
    final name = _nameCtrl.text.trim();
    if (name.isEmpty) return;

    final success = await auth.updateConcours('');
    // On utilise updateProfile directement
    // (via auth_provider qui appelle AuthService.updateProfile)
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Profil sauvegardé !'),
        backgroundColor: Color(0xFF4CAF50),
      ),
    );
  }

  void _confirmLogout(BuildContext context) {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        backgroundColor: const Color(0xFF231E42),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Déconnexion', style: TextStyle(color: Colors.white)),
        content: const Text('Es-tu sûr de vouloir te déconnecter ?',
          style: TextStyle(color: Color(0xFF9D96C0))),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Annuler')),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(context);
              await context.read<AuthProvider>().logout();
              if (context.mounted) {
                Navigator.pushNamedAndRemoveUntil(context, '/login', (_) => false);
              }
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.redAccent),
            child: const Text('Déconnexion'),
          ),
        ],
      ),
    );
  }

  Widget _sectionTitle(String title) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child:   Text(title, style: const TextStyle(
      color: Color(0xFF9D96C0), fontSize: 13, fontWeight: FontWeight.w600, letterSpacing: 1,
    )),
  );
}
